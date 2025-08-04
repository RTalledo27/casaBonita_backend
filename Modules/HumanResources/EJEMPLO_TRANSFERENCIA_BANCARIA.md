# Ejemplo de Implementación de Transferencia Bancaria para Comisiones

## Estructura de Archivos Propuesta

```
Modules/HumanResources/
├── app/
│   ├── Models/
│   │   ├── Commission.php (existente)
│   │   └── BankTransfer.php (nuevo)
│   ├── Services/
│   │   ├── CommissionService.php (existente)
│   │   └── BankTransferService.php (nuevo)
│   ├── Repositories/
│   │   ├── CommissionRepository.php (existente)
│   │   └── BankTransferRepository.php (nuevo)
│   └── Http/Controllers/
│       ├── CommissionController.php (existente)
│       └── BankTransferController.php (nuevo)
├── database/migrations/
│   └── create_bank_transfers_table.php (nuevo)
└── routes/
    └── api.php (modificar)
```

## 1. Migración para Tabla de Transferencias Bancarias

```php
<?php
// Modules/HumanResources/database/migrations/xxxx_create_bank_transfers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id('transfer_id');
            $table->unsignedBigInteger('commission_id'); // Relación con comisión
            $table->string('bank_name'); // Nombre del banco
            $table->string('account_number'); // Número de cuenta
            $table->string('account_holder_name'); // Nombre del titular
            $table->string('account_type'); // Tipo de cuenta (ahorro, corriente)
            $table->decimal('transfer_amount', 10, 2); // Monto a transferir
            $table->enum('transfer_status', ['pendiente', 'procesando', 'completada', 'fallida'])
                  ->default('pendiente');
            $table->string('transaction_reference')->nullable(); // Referencia bancaria
            $table->text('transfer_notes')->nullable(); // Notas adicionales
            $table->timestamp('scheduled_date')->nullable(); // Fecha programada
            $table->timestamp('processed_date')->nullable(); // Fecha de procesamiento
            $table->timestamps();
            
            // Índices y relaciones
            $table->foreign('commission_id')
                  ->references('commission_id')
                  ->on('commissions')
                  ->onDelete('cascade');
            
            $table->index(['transfer_status', 'scheduled_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bank_transfers');
    }
};
```

## 2. Modelo BankTransfer

```php
<?php
// Modules/HumanResources/app/Models/BankTransfer.php

namespace Modules\HumanResources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BankTransfer extends Model
{
    use HasFactory;

    protected $primaryKey = 'transfer_id';

    protected $fillable = [
        'commission_id',
        'bank_name',
        'account_number',
        'account_holder_name',
        'account_type',
        'transfer_amount',
        'transfer_status',
        'transaction_reference',
        'transfer_notes',
        'scheduled_date',
        'processed_date'
    ];

    protected $casts = [
        'transfer_amount' => 'decimal:2',
        'scheduled_date' => 'datetime',
        'processed_date' => 'datetime'
    ];

    // Relación con Commission
    public function commission()
    {
        return $this->belongsTo(Commission::class, 'commission_id', 'commission_id');
    }

    // Scopes para filtrar por estado
    public function scopePending($query)
    {
        return $query->where('transfer_status', 'pendiente');
    }

    public function scopeProcessing($query)
    {
        return $query->where('transfer_status', 'procesando');
    }

    public function scopeCompleted($query)
    {
        return $query->where('transfer_status', 'completada');
    }
}
```

## 3. Servicio de Transferencias Bancarias

```php
<?php
// Modules/HumanResources/app/Services/BankTransferService.php

namespace Modules\HumanResources\Services;

use Modules\HumanResources\Models\BankTransfer;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Repositories\BankTransferRepository;
use Modules\HumanResources\Repositories\CommissionRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BankTransferService
{
    public function __construct(
        private BankTransferRepository $bankTransferRepository,
        private CommissionRepository $commissionRepository
    ) {}

    /**
     * Crear una transferencia bancaria para una comisión
     */
    public function createTransferForCommission(int $commissionId, array $bankData): BankTransfer
    {
        // Verificar que la comisión existe y está pendiente
        $commission = $this->commissionRepository->findById($commissionId);
        
        if (!$commission) {
            throw new \Exception('Comisión no encontrada');
        }
        
        if ($commission->payment_status !== 'pendiente') {
            throw new \Exception('La comisión ya ha sido procesada');
        }

        // Crear la transferencia bancaria
        $transferData = [
            'commission_id' => $commissionId,
            'bank_name' => $bankData['bank_name'],
            'account_number' => $bankData['account_number'],
            'account_holder_name' => $bankData['account_holder_name'],
            'account_type' => $bankData['account_type'],
            'transfer_amount' => $commission->commission_amount,
            'transfer_status' => 'pendiente',
            'transfer_notes' => $bankData['notes'] ?? null,
            'scheduled_date' => $bankData['scheduled_date'] ?? now()
        ];

        return $this->bankTransferRepository->create($transferData);
    }

    /**
     * Procesar transferencias bancarias pendientes
     */
    public function processPendingTransfers(): array
    {
        $pendingTransfers = $this->bankTransferRepository->getPendingTransfers();
        $results = [];

        foreach ($pendingTransfers as $transfer) {
            try {
                $result = $this->processTransfer($transfer);
                $results[] = $result;
            } catch (\Exception $e) {
                Log::error('Error procesando transferencia: ' . $e->getMessage(), [
                    'transfer_id' => $transfer->transfer_id
                ]);
                
                // Marcar como fallida
                $this->bankTransferRepository->updateStatus(
                    $transfer->transfer_id, 
                    'fallida'
                );
            }
        }

        return $results;
    }

    /**
     * Procesar una transferencia individual
     */
    private function processTransfer(BankTransfer $transfer): array
    {
        DB::beginTransaction();
        
        try {
            // 1. Marcar transferencia como procesando
            $this->bankTransferRepository->updateStatus(
                $transfer->transfer_id, 
                'procesando'
            );

            // 2. Simular llamada a API bancaria (aquí integrarías con tu banco)
            $bankResponse = $this->callBankAPI($transfer);
            
            if ($bankResponse['success']) {
                // 3. Marcar transferencia como completada
                $this->bankTransferRepository->update($transfer->transfer_id, [
                    'transfer_status' => 'completada',
                    'transaction_reference' => $bankResponse['reference'],
                    'processed_date' => now()
                ]);
                
                // 4. Marcar comisión como pagada
                $this->commissionRepository->markAsPaid($transfer->commission_id);
                
                DB::commit();
                
                return [
                    'transfer_id' => $transfer->transfer_id,
                    'status' => 'success',
                    'reference' => $bankResponse['reference']
                ];
            } else {
                throw new \Exception($bankResponse['error']);
            }
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Simular llamada a API bancaria
     * En producción, aquí integrarías con la API real de tu banco
     */
    private function callBankAPI(BankTransfer $transfer): array
    {
        // Simulación de respuesta bancaria
        // En producción, aquí harías la llamada real a la API del banco
        
        // Ejemplo con cURL o Guzzle:
        /*
        $response = Http::post('https://api.banco.com/transfers', [
            'account_number' => $transfer->account_number,
            'amount' => $transfer->transfer_amount,
            'reference' => 'COMM-' . $transfer->commission_id,
            'description' => 'Pago de comisión'
        ]);
        */
        
        // Simulación para el ejemplo
        $success = rand(1, 10) > 2; // 80% de éxito
        
        if ($success) {
            return [
                'success' => true,
                'reference' => 'TXN-' . time() . '-' . $transfer->transfer_id
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Error en la transferencia bancaria'
            ];
        }
    }

    /**
     * Crear transferencias masivas para múltiples comisiones
     */
    public function createBulkTransfers(array $commissionIds, array $bankData): array
    {
        $results = [];
        
        foreach ($commissionIds as $commissionId) {
            try {
                $transfer = $this->createTransferForCommission($commissionId, $bankData);
                $results[] = [
                    'commission_id' => $commissionId,
                    'transfer_id' => $transfer->transfer_id,
                    'status' => 'created'
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'commission_id' => $commissionId,
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
}
```

## 4. Controlador de Transferencias

```php
<?php
// Modules/HumanResources/app/Http/Controllers/BankTransferController.php

namespace Modules\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\HumanResources\Services\BankTransferService;
use Modules\HumanResources\Repositories\BankTransferRepository;

class BankTransferController extends Controller
{
    public function __construct(
        private BankTransferService $bankTransferService,
        private BankTransferRepository $bankTransferRepository
    ) {}

    /**
     * Crear transferencia para una comisión
     */
    public function createTransfer(Request $request): JsonResponse
    {
        $request->validate([
            'commission_id' => 'required|integer|exists:commissions,commission_id',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_holder_name' => 'required|string|max:100',
            'account_type' => 'required|in:ahorro,corriente',
            'notes' => 'nullable|string|max:500',
            'scheduled_date' => 'nullable|date|after_or_equal:today'
        ]);

        try {
            $transfer = $this->bankTransferService->createTransferForCommission(
                $request->commission_id,
                $request->only(['bank_name', 'account_number', 'account_holder_name', 'account_type', 'notes', 'scheduled_date'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Transferencia bancaria creada exitosamente',
                'data' => $transfer
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Crear transferencias masivas
     */
    public function createBulkTransfers(Request $request): JsonResponse
    {
        $request->validate([
            'commission_ids' => 'required|array|min:1',
            'commission_ids.*' => 'integer|exists:commissions,commission_id',
            'bank_name' => 'required|string|max:100',
            'account_number' => 'required|string|max:50',
            'account_holder_name' => 'required|string|max:100',
            'account_type' => 'required|in:ahorro,corriente',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            $results = $this->bankTransferService->createBulkTransfers(
                $request->commission_ids,
                $request->only(['bank_name', 'account_number', 'account_holder_name', 'account_type', 'notes'])
            );

            return response()->json([
                'success' => true,
                'message' => 'Transferencias bancarias procesadas',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Procesar transferencias pendientes
     */
    public function processPendingTransfers(): JsonResponse
    {
        try {
            $results = $this->bankTransferService->processPendingTransfers();

            return response()->json([
                'success' => true,
                'message' => 'Transferencias procesadas',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de transferencias
     */
    public function getTransferHistory(Request $request): JsonResponse
    {
        $filters = $request->only(['commission_id', 'transfer_status', 'date_from', 'date_to']);
        $transfers = $this->bankTransferRepository->getWithFilters($filters);

        return response()->json([
            'success' => true,
            'data' => $transfers
        ]);
    }
}
```

## 5. Rutas API

```php
// Modules/HumanResources/routes/api.php (agregar estas rutas)

Route::prefix('bank-transfers')->group(function () {
    Route::post('/', [BankTransferController::class, 'createTransfer']);
    Route::post('/bulk', [BankTransferController::class, 'createBulkTransfers']);
    Route::post('/process-pending', [BankTransferController::class, 'processPendingTransfers']);
    Route::get('/history', [BankTransferController::class, 'getTransferHistory']);
});
```

## 6. Componente Frontend (Angular)

```typescript
// casaBonita_frontend/src/app/components/bank-transfer-modal.component.ts

import { Component, Input, Output, EventEmitter } from '@angular/core';
import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { BankTransferService } from '../services/bank-transfer.service';

@Component({
  selector: 'app-bank-transfer-modal',
  template: `
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
      <div class="bg-white rounded-lg p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Configurar Transferencia Bancaria</h3>
        
        <form [formGroup]="transferForm" (ngSubmit)="onSubmit()">
          <!-- Información bancaria -->
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700">Banco</label>
              <input type="text" formControlName="bank_name" 
                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Número de Cuenta</label>
              <input type="text" formControlName="account_number" 
                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Titular de la Cuenta</label>
              <input type="text" formControlName="account_holder_name" 
                     class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Tipo de Cuenta</label>
              <select formControlName="account_type" 
                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                <option value="ahorro">Ahorro</option>
                <option value="corriente">Corriente</option>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700">Notas (Opcional)</label>
              <textarea formControlName="notes" rows="3"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
              </textarea>
            </div>
          </div>
          
          <!-- Botones -->
          <div class="flex justify-end space-x-3 mt-6">
            <button type="button" (click)="onCancel()" 
                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-md hover:bg-gray-300">
              Cancelar
            </button>
            <button type="submit" [disabled]="transferForm.invalid || isLoading"
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50">
              {{ isLoading ? 'Procesando...' : 'Crear Transferencia' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  `
})
export class BankTransferModalComponent {
  @Input() commissionIds: number[] = [];
  @Output() transferCreated = new EventEmitter<any>();
  @Output() modalClosed = new EventEmitter<void>();
  
  transferForm: FormGroup;
  isLoading = false;
  
  constructor(
    private fb: FormBuilder,
    private bankTransferService: BankTransferService
  ) {
    this.transferForm = this.fb.group({
      bank_name: ['', [Validators.required, Validators.maxLength(100)]],
      account_number: ['', [Validators.required, Validators.maxLength(50)]],
      account_holder_name: ['', [Validators.required, Validators.maxLength(100)]],
      account_type: ['ahorro', Validators.required],
      notes: ['', Validators.maxLength(500)]
    });
  }
  
  onSubmit() {
    if (this.transferForm.valid) {
      this.isLoading = true;
      
      const transferData = {
        ...this.transferForm.value,
        commission_ids: this.commissionIds
      };
      
      this.bankTransferService.createBulkTransfers(transferData).subscribe({
        next: (response) => {
          this.transferCreated.emit(response);
          this.isLoading = false;
        },
        error: (error) => {
          console.error('Error creando transferencia:', error);
          this.isLoading = false;
        }
      });
    }
  }
  
  onCancel() {
    this.modalClosed.emit();
  }
}
```

## 7. Servicio Frontend

```typescript
// casaBonita_frontend/src/app/services/bank-transfer.service.ts

import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class BankTransferService {
  private apiUrl = `${environment.apiUrl}/hr/bank-transfers`;
  
  constructor(private http: HttpClient) {}
  
  createTransfer(transferData: any): Observable<any> {
    return this.http.post(`${this.apiUrl}`, transferData);
  }
  
  createBulkTransfers(transferData: any): Observable<any> {
    return this.http.post(`${this.apiUrl}/bulk`, transferData);
  }
  
  processPendingTransfers(): Observable<any> {
    return this.http.post(`${this.apiUrl}/process-pending`, {});
  }
  
  getTransferHistory(filters: any = {}): Observable<any> {
    return this.http.get(`${this.apiUrl}/history`, { params: filters });
  }
}
```

## 8. Integración en el Componente de Comisiones

```typescript
// Modificar advisor-commissions.component.ts para agregar funcionalidad de transferencia

// Agregar al template:
/*
<button (click)="openBankTransferModal()" 
        class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg">
  💳 Transferencia Bancaria
</button>

<app-bank-transfer-modal 
  *ngIf="showBankTransferModal"
  [commissionIds]="selectedCommissionIds"
  (transferCreated)="onTransferCreated($event)"
  (modalClosed)="closeBankTransferModal()">
</app-bank-transfer-modal>
*/

// Agregar al componente:
showBankTransferModal = false;
selectedCommissionIds: number[] = [];

openBankTransferModal() {
  // Obtener IDs de comisiones pendientes
  this.selectedCommissionIds = this.commissions()
    .filter(c => c.payment_status === 'pendiente')
    .map(c => c.commission_id);
  
  if (this.selectedCommissionIds.length === 0) {
    alert('No hay comisiones pendientes para transferir');
    return;
  }
  
  this.showBankTransferModal = true;
}

closeBankTransferModal() {
  this.showBankTransferModal = false;
}

onTransferCreated(response: any) {
  console.log('Transferencias creadas:', response);
  this.showBankTransferModal = false;
  // Recargar datos de comisiones
  this.loadCommissions();
}
```

## Consideraciones de Seguridad

1. **Encriptación**: Los datos bancarios deben estar encriptados en la base de datos
2. **Autenticación**: Implementar autenticación robusta para las APIs bancarias
3. **Logs de Auditoría**: Registrar todas las transacciones para auditoría
4. **Validación**: Validar todos los datos antes de enviar a la API bancaria
5. **Rate Limiting**: Implementar límites de velocidad para evitar abuso

## Próximos Pasos

1. Ejecutar las migraciones
2. Implementar los modelos y servicios
3. Configurar las rutas API
4. Integrar con la API bancaria real
5. Implementar el frontend
6. Realizar pruebas exhaustivas
7. Configurar monitoreo y alertas

Este ejemplo proporciona una base sólida para implementar transferencias bancarias en el sistema de comisiones, manteniendo la seguridad y escalabilidad necesarias.