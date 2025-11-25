<?php

namespace Modules\HumanResources\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\HumanResources\Models\Commission;

class CommissionRepository
{
    public function __construct(protected Commission $model) {}

    

   


    public function markAsPaid(int $id): bool
    {
        $commission = $this->findById($id);
        if ($commission) {
            return $commission->update([
                'payment_status' => 'pagado',
                'payment_date' => now()
            ]);
        }
        return false;
    }

    public function getEmployeeCommissionSummary(int $employeeId, int $month, int $year): array
    {
        $commissions = $this->model->where('employee_id', $employeeId)
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->get();

        return [
            'total_commissions' => $commissions->sum('commission_amount'),
            'paid_commissions' => $commissions->where('payment_status', 'pagado')->sum('commission_amount'),
            'pending_commissions' => $commissions->where('payment_status', 'pendiente')->sum('commission_amount'),
            'commissions_count' => $commissions->count()
        ];
    }


    public function getAll(array $filters = []): Collection
    {
        $query = $this->model->with(['employee.user', 'contract.lot.manzana', 'contract.reservation.lot.manzana', 'childCommissions', 'parentCommission']);

        // Mostrar todas las comisiones por defecto
        // Solo aplicar filtro payable si se especifica explícitamente
        if (isset($filters['only_payable']) && $filters['only_payable']) {
            $query->payable();
        }

        // Mantener compatibilidad con filtros existentes
        if (isset($filters['include_split_payments'])) {
            if ($filters['include_split_payments']) {
                // Si se incluyen split payments, mostrar todas las comisiones
                $query = $this->model->with(['employee.user', 'contract', 'childCommissions', 'parentCommission']);
            } else {
                // Si NO se incluyen split payments, solo mostrar comisiones padre (no payables)
                $query->where('is_payable', false);
            }
        }

        if (isset($filters['only_split_payments']) && $filters['only_split_payments']) {
            $query->payableDivisions();
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['period_month'])) {
            $query->where('period_month', $filters['period_month']);
        }

        if (isset($filters['period_year'])) {
            $query->where('period_year', $filters['period_year']);
        }

        if (isset($filters['commission_period'])) {
            $query->where('commission_period', $filters['commission_period']);
        }

        if (isset($filters['payment_period'])) {
            $query->where('payment_period', $filters['payment_period']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['employee.user', 'contract.lot.manzana', 'contract.reservation.lot.manzana', 'childCommissions', 'parentCommission']);

        // Mostrar todas las comisiones por defecto
        // Solo aplicar filtro payable si se especifica explícitamente
        if (isset($filters['only_payable']) && $filters['only_payable']) {
            $query->payable();
        }

        // Mantener compatibilidad con filtros existentes
        if (isset($filters['include_split_payments'])) {
            if ($filters['include_split_payments']) {
                // Si se incluyen split payments, mostrar todas las comisiones
                // NO sobrescribir la query, mantener las relaciones ya cargadas
            } else {
                // Si NO se incluyen split payments, solo mostrar comisiones padre (no payables)
                $query->where('is_payable', false);
            }
        }

        if (isset($filters['only_split_payments']) && $filters['only_split_payments']) {
            $query->payableDivisions();
        }

        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['period_month'])) {
            $query->where('period_month', $filters['period_month']);
        }

        if (isset($filters['period_year'])) {
            $query->where('period_year', $filters['period_year']);
        }

        if (isset($filters['commission_period'])) {
            $query->where('commission_period', $filters['commission_period']);
        }

        if (isset($filters['payment_period'])) {
            $query->where('payment_period', $filters['payment_period']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function findById(int $id): ?Commission
    {
        return $this->model->with(['employee.user', 'contract.lot.manzana', 'contract.reservation.lot.manzana'])->find($id);
    }

    public function create(array $data): Commission
    {
        return $this->model->create($data);
    }

    public function update(int $id, array $data): ?Commission
    {
        $commission = $this->findById($id);
        if (!$commission) {
            return null;
        }

        $commission->update($data);
        return $commission->fresh(['employee.user', 'contract']);
    }

    public function delete(int $id): bool
    {
        $commission = $this->findById($id);
        if (!$commission) {
            return false;
        }

        return $commission->delete();
    }

    public function getPendingForPeriod(int $month, int $year): Collection
    {
        return $this->model->with(['employee.user', 'contract'])
            ->payable()
            ->pending()
            ->byPeriod($month, $year)
            ->get();
    }


    

    public function getByEmployee(int $employeeId, int $month = null, int $year = null): Collection
    {
        $query = $this->model->with(['contract.lot.manzana', 'contract.reservation.lot.manzana'])
            ->byEmployee($employeeId);

        if ($month && $year) {
            $query->byPeriod($month, $year);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function markMultipleAsPaid(array $commissionIds): int
    {
        $updatedCount = 0;
        
        // Procesar cada comisión individualmente para calcular payment_date basado en payment_period
        foreach ($commissionIds as $commissionId) {
            $commission = $this->model->find($commissionId);
            if (!$commission) {
                continue;
            }
            
            // VALIDACIÓN: Evitar doble pago
            // Si es una comisión padre, puede pagarse independientemente de las hijas
            // La sincronización se maneja después del pago exitoso
            if (!$commission->parent_commission_id) {
                // Las comisiones padre pueden pagarse siempre
                // La lógica de sincronización con hijas se maneja más abajo
            }
            
            // Si es una comisión hija, verificar que la padre no esté pagada
            if ($commission->parent_commission_id) {
                $parentCommission = $commission->parentCommission;
                if ($parentCommission && $parentCommission->payment_status === 'pagado') {
                    // Si la padre está pagada, no permitir pagar la hija
                    continue;
                }
            }
            
            // Calcular payment_date basado en payment_period
            $paymentDate = now()->toDateString(); // Fallback por defecto
            
            if ($commission->payment_period) {
                // Extraer año y mes del payment_period (formato: YYYY-MM-P1 o YYYY-MM-P2)
                if (preg_match('/^(\d{4})-(\d{2})-P[12]$/', $commission->payment_period, $matches)) {
                    $year = $matches[1];
                    $month = $matches[2];
                    $paymentDate = "{$year}-{$month}-01";
                }
            }
            
            $updated = $this->model->where('commission_id', $commissionId)
                ->update([
                    'payment_status' => 'pagado',
                    'payment_date' => $paymentDate,
                    'status' => 'fully_paid',
                    'updated_at' => now(),
                ]);
                
            if ($updated) {
                $updatedCount += $updated;
                
                // SINCRONIZACIÓN: Si es una comisión padre, marcar todas las hijas como pagadas
                if (!$commission->parent_commission_id) {
                    $commission->childCommissions()->update([
                        'payment_status' => 'pagado',
                        'payment_date' => $paymentDate,
                        'status' => 'fully_paid',
                        'updated_at' => now(),
                    ]);
                }
                
                // SINCRONIZACIÓN: Si es una comisión hija, verificar si todas las hermanas están pagadas
                // para marcar la padre como pagada también
                if ($commission->parent_commission_id) {
                    $parentCommission = $commission->parentCommission;
                    if ($parentCommission) {
                        $totalChildren = $parentCommission->childCommissions()->count();
                        $paidChildren = $parentCommission->childCommissions()->where('payment_status', 'pagado')->count();
                        
                        // Si todas las hijas están pagadas, marcar la padre como pagada
                        if ($paidChildren >= $totalChildren) {
                            $parentCommission->update([
                                'payment_status' => 'pagado',
                                'payment_date' => $paymentDate,
                                'status' => 'fully_paid',
                                'updated_at' => now(),
                            ]);
                        }
                    }
                }
            }
        }
        
        return $updatedCount;
    }

    /**
     * Crea un pago dividido para una comisión
     */
    public function createSplitPayment(int $parentCommissionId, array $paymentData): Commission
    {
        $paymentData['parent_commission_id'] = $parentCommissionId;
        return $this->model->create($paymentData);
    }

    /**
     * Obtiene comisiones por período de generación
     */
    public function getByCommissionPeriod(string $period): Collection
    {
        return $this->model->with(['employee.user', 'contract'])
            ->where('commission_period', $period)
            ->get();
    }

    /**
     * Obtiene comisiones por período de pago
     */
    public function getByPaymentPeriod(string $period): Collection
    {
        return $this->model->with(['employee.user', 'contract'])
            ->where('payment_period', $period)
            ->get();
    }

    /**
     * Obtiene comisiones pendientes de pago para un período específico
     */
    public function getPendingForCommissionPeriod(string $period): Collection
    {
        return $this->model->with(['employee.user', 'contract'])
            ->where('commission_period', $period)
            ->where('status', 'generated')
            ->get();
    }

    /**
     * Procesa un pago dividido
     */
    public function processSplitPayment(int $commissionId, float $percentage, string $paymentPeriod, int $paymentPart): ?Commission
    {
        $originalCommission = $this->findById($commissionId);
        if (!$originalCommission) {
            return null;
        }

        $paymentAmount = ($originalCommission->commission_amount * $percentage) / 100;

        // Calcular payment_date basado en payment_period
        $paymentDate = now()->toDateString(); // Fallback por defecto
        
        if ($paymentPeriod) {
            // Extraer año y mes del payment_period (formato: YYYY-MM-P1 o YYYY-MM-P2)
            if (preg_match('/^(\d{4})-(\d{2})-P[12]$/', $paymentPeriod, $matches)) {
                $year = $matches[1];
                $month = $matches[2];
                $paymentDate = "{$year}-{$month}-01";
            }
        }

        // Crear el registro del pago
        $splitPayment = $this->createSplitPayment($commissionId, [
            'employee_id' => $originalCommission->employee_id,
            'contract_id' => $originalCommission->contract_id,
            'commission_type' => $originalCommission->commission_type,
            'sale_amount' => $originalCommission->sale_amount,
            'installment_plan' => $originalCommission->installment_plan,
            'commission_percentage' => $originalCommission->commission_percentage,
            'commission_amount' => $paymentAmount,
            'payment_status' => 'pagado',
            'payment_date' => $paymentDate,
            'period_month' => $originalCommission->period_month,
            'period_year' => $originalCommission->period_year,
            'commission_period' => $originalCommission->commission_period,
            'payment_period' => $paymentPeriod,
            'payment_percentage' => $percentage,
            'status' => 'fully_paid',
            'payment_part' => $paymentPart,
            'notes' => "Pago dividido - Parte {$paymentPart} ({$percentage}%)"
        ]);

        // Actualizar el estado de la comisión original
        $totalPaid = $originalCommission->childCommissions()->sum('payment_percentage');
        $newStatus = ($totalPaid >= 100) ? 'fully_paid' : 'partially_paid';
        
        $originalCommission->update([
            'status' => $newStatus,
            'updated_at' => now()
        ]);

        return $splitPayment;
    }

    /**
     * Obtiene el resumen de pagos divididos para una comisión
     */
    public function getSplitPaymentSummary(int $commissionId): array
    {
        $commission = $this->findById($commissionId);
        if (!$commission) {
            return [];
        }

        $splitPayments = $commission->childCommissions;
        $totalPaid = $splitPayments->sum('payment_percentage');
        $totalAmount = $splitPayments->sum('commission_amount');
        $remainingPercentage = 100 - $totalPaid;
        $remainingAmount = ($commission->commission_amount * $remainingPercentage) / 100;

        return [
            'original_amount' => $commission->commission_amount,
            'total_paid_percentage' => $totalPaid,
            'total_paid_amount' => $totalAmount,
            'remaining_percentage' => $remainingPercentage,
            'remaining_amount' => $remainingAmount,
            'payments_count' => $splitPayments->count(),
            'payments' => $splitPayments->map(function ($payment) {
                return [
                    'payment_id' => $payment->commission_id,
                    'payment_part' => $payment->payment_part,
                    'percentage' => $payment->payment_percentage,
                    'amount' => $payment->commission_amount,
                    'payment_period' => $payment->payment_period,
                    'payment_date' => $payment->payment_date,
                    'status' => $payment->status
                ];
            })
        ];
    }

    public function getTotalCommissionsForPeriod(int $month, int $year): float
    {
        return $this->model->byPeriod($month, $year)
            ->sum('commission_amount');
    }

    /**
     * Obtiene comisiones que requieren verificación de pagos
     */
    public function getCommissionsRequiringVerification(array $filters = [])
    {
        $query = $this->model->with(['employee.user', 'contract', 'paymentVerifications'])
            ->requiresVerification();

        // Aplicar filtros
        if (isset($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (isset($filters['payment_verification_status'])) {
            $query->byVerificationStatus($filters['payment_verification_status']);
        }

        if (isset($filters['period_month'])) {
            $query->where('period_month', $filters['period_month']);
        }

        if (isset($filters['period_year'])) {
            $query->where('period_year', $filters['period_year']);
        }

        if (isset($filters['commission_period'])) {
            $query->where('commission_period', $filters['commission_period']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('employee.user', function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            })->orWhereHas('contract', function($q) use ($search) {
                $q->where('contract_number', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc');
    }
}
