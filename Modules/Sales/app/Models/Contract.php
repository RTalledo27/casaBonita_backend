<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Accounting\Models\Invoice;
use Modules\HumanResources\Models\Commission;
use Modules\HumanResources\Models\Employee;

// use Modules\Sales\Database\Factories\ContractFactory;

class Contract extends Model
{
    use HasFactory;


    protected $primaryKey = 'contract_id';
    public    $timestamps  = false;
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'reservation_id',
        'client_id', // Cliente directo (para contratos sin reserva)
        'lot_id', // Lote directo (para contratos sin reserva)
        'advisor_id', // Asesor inmobiliario que gestiona el contrato
        'previous_contract_id',
        'contract_number',
        'contract_date', // Fecha del contrato
        'sign_date',
        'base_price', // Precio lista / base
        'unit_price', // Precio unitario (venta antes de descuento)
        'total_price',
        'discount', // 🔥 Descuento aplicado a la venta
        'down_payment',
        'financing_amount',
        'interest_rate',
        'term_months',
        'monthly_payment',
        'balloon_payment',
        'currency',
        'status',
        'sale_type', // 🔥 Tipo de venta: 'cash' o 'financed'
        'notes', // Notas del contrato
        'transferred_amount_from_previous_contract',
        // Nuevos campos financieros migrados desde Lot:
        'funding',
        'bpp',
        'bfh',
        'initial_quota',
        // Campos de trazabilidad
        'source',
        'logicware_data'
    ];


    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'contract_date' => 'date',
        'sign_date' => 'date',
        'base_price' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'financing_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'monthly_payment' => 'decimal:2',
        'balloon_payment' => 'decimal:2',
        'transferred_amount_from_previous_contract' => 'decimal:2',
        // Nuevos campos financieros:
        'funding' => 'decimal:2',
        'bpp' => 'decimal:2',
        'bfh' => 'decimal:2',
        'initial_quota' => 'decimal:2',
        'logicware_data' => 'array' // Agregar casting para JSON
    ];




    // --- RELACIONES ---
    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'reservation_id');
    }

    // Relaciones directas para contratos sin reserva
    public function client()
    {
        return $this->belongsTo(\Modules\CRM\Models\Client::class, 'client_id');
    }

    public function lot()
    {
        return $this->belongsTo(\Modules\Inventory\Models\Lot::class, 'lot_id');
    }

    public function advisor()
    {
        return $this->belongsTo(Employee::class, 'advisor_id', 'employee_id');
    }

    public function previousContract()
    {
        return $this->belongsTo(Contract::class, 'previous_contract_id');
    }

    public function paymentSchedules()
    {
        return $this->hasMany(PaymentSchedule::class, 'contract_id');
    }

    public function schedules() // Alias para paymentSchedules, si se usa en algún lugar
    {
        return $this->hasMany(PaymentSchedule::class, 'contract_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'contract_id');
    }

    public function approvals()
    {
        return $this->hasMany(ContractApproval::class, 'contract_id');
    }

    public function commission()
    {
        return $this->hasOne(Commission::class, 'contract_id', 'contract_id');
    }

    // --- MÉTODOS AUXILIARES ---

    /**
     * Calcula el pago mensual basado en los datos del contrato
     */
    public function calculateMonthlyPayment(): float
    {
        if ($this->financing_amount <= 0 || $this->term_months <= 0 || $this->interest_rate <= 0) {
            return 0;
        }

        $monthlyRate = $this->interest_rate / 12;
        $numerator = $this->financing_amount * $monthlyRate * pow(1 + $monthlyRate, $this->term_months);
        $denominator = pow(1 + $monthlyRate, $this->term_months) - 1;

        return round($numerator / $denominator, 2);
    }

    /**
     * Valida que los montos financieros sean consistentes
     */
    public function validateFinancialConsistency(): bool
    {
        return abs(($this->down_payment + $this->financing_amount) - $this->total_price) < 0.01;
    }

    // --- MÉTODOS AUXILIARES PARA CONTRATOS DIRECTOS ---

    /**
     * Obtiene el cliente del contrato (directo o desde reserva)
     */
    public function getClient()
    {
        if ($this->client_id) {
            return $this->client;
        }
        
        if ($this->reservation_id && $this->reservation) {
            return $this->reservation->client;
        }
        
        return null;
    }

    /**
     * Obtiene el lote del contrato (directo o desde reserva)
     */
    public function getLot()
    {
        if ($this->lot_id) {
            return $this->lot;
        }
        
        if ($this->reservation_id && $this->reservation) {
            return $this->reservation->lot;
        }
        
        return null;
    }

    /**
     * Obtiene el asesor del contrato (directo o desde reserva)
     */
    public function getAdvisor()
    {
        if ($this->advisor_id) {
            return $this->advisor;
        }
        
        if ($this->reservation_id && $this->reservation) {
            return $this->reservation->advisor;
        }
        
        return null;
    }

    /**
     * Verifica si es un contrato directo (sin reserva)
     */
    public function isDirectContract(): bool
    {
        return $this->client_id !== null && $this->lot_id !== null;
    }

    /**
     * Verifica si es un contrato desde reserva
     */
    public function isFromReservation(): bool
    {
        return $this->reservation_id !== null;
    }

    /**
     * Obtiene el nombre completo del cliente
     */
    public function getClientName(): ?string
    {
        $client = $this->getClient();
        if ($client) {
            return $client->first_name . ' ' . $client->last_name;
        }
        return null;
    }

    /**
     * Obtiene el nombre/número del lote
     */
    public function getLotName(): ?string
    {
        $lot = $this->getLot();
        if ($lot) {
            // Usar external_code (ej: "I-76") o construir desde manzana + num_lot
            return $lot->external_code ?? ($lot->manzana ? $lot->manzana->name . '-' . $lot->num_lot : 'Lote ' . $lot->num_lot);
        }
        return null;
    }

    /**
     * Obtiene el nombre de la manzana
     */
    public function getManzanaName(): ?string
    {
        $lot = $this->getLot();
        if ($lot && $lot->manzana) {
            return $lot->manzana->name;
        }
        return null;
    }

    /**
     * Obtiene el área del lote en m²
     */
    public function getArea(): ?float
    {
        $lot = $this->getLot();
        if ($lot) {
            return $lot->area_m2;
        }
        return null;
    }
}
