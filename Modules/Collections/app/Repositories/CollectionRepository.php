<?php

namespace Modules\Collections\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Modules\Collections\Models\AccountReceivable;
use Modules\Collections\Models\CustomerPayment;

class CollectionRepository
{
    protected $accountReceivable;
    protected $customerPayment;

    public function __construct(AccountReceivable $accountReceivable, CustomerPayment $customerPayment)
    {
        $this->accountReceivable = $accountReceivable;
        $this->customerPayment = $customerPayment;
    }

    /**
     * Obtener cuentas por cobrar con filtros
     */
    public function getAccountsReceivable(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->accountReceivable->with(['client', 'contract', 'collector']);

        // Aplicar filtros
        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['collector_id'])) {
            $query->where('assigned_collector_id', $filters['collector_id']);
        }

        if (isset($filters['overdue_only']) && $filters['overdue_only']) {
            $query->overdue();
        }

        if (isset($filters['due_date_from'])) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }

        if (isset($filters['due_date_to'])) {
            $query->where('due_date', '<=', $filters['due_date_to']);
        }

        return $query->orderBy('due_date')->paginate($perPage);
    }

    /**
     * Obtener cuenta por cobrar por ID
     */
    public function findAccountReceivable(int $arId): ?AccountReceivable
    {
        return $this->accountReceivable->with(['client', 'contract', 'collector', 'payments.processor'])
            ->find($arId);
    }

    /**
     * Crear nueva cuenta por cobrar
     */
    public function createAccountReceivable(array $data): AccountReceivable
    {
        $data['ar_number'] = $this->generateARNumber();
        return $this->accountReceivable->create($data);
    }

    /**
     * Actualizar cuenta por cobrar
     */
    public function updateAccountReceivable(int $arId, array $data): bool
    {
        return $this->accountReceivable->where('ar_id', $arId)->update($data);
    }

    /**
     * Obtener pagos con filtros
     */
    public function getPayments(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->customerPayment->with(['client', 'accountReceivable', 'processor']);

        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->byDateRange($filters['date_from'], $filters['date_to']);
        }

        if (isset($filters['processed_by'])) {
            $query->where('processed_by', $filters['processed_by']);
        }

        return $query->orderBy('payment_date', 'desc')->paginate($perPage);
    }

    /**
     * Crear nuevo pago
     */
    public function createPayment(array $data): CustomerPayment
    {
        $data['payment_number'] = CustomerPayment::generatePaymentNumber();
        return $this->customerPayment->create($data);
    }

    /**
     * Obtener reporte de antigüedad
     */
    public function getAgingReport(?int $clientId = null): array
    {
        $query = $this->accountReceivable->where('outstanding_amount', '>', 0);

        if ($clientId) {
            $query->where('client_id', $clientId);
        }

        $receivables = $query->get();

        $aging = [
            'current' => 0,
            '1_30_days' => 0,
            '31_60_days' => 0,
            '61_90_days' => 0,
            'over_90_days' => 0,
            'total' => 0
        ];

        foreach ($receivables as $ar) {
            $range = $ar->aging_range;

            switch ($range) {
                case 'current':
                    $aging['current'] += $ar->outstanding_amount;
                    break;
                case '1-30':
                    $aging['1_30_days'] += $ar->outstanding_amount;
                    break;
                case '31-60':
                    $aging['31_60_days'] += $ar->outstanding_amount;
                    break;
                case '61-90':
                    $aging['61_90_days'] += $ar->outstanding_amount;
                    break;
                case 'over-90':
                    $aging['over_90_days'] += $ar->outstanding_amount;
                    break;
                default:
                    // Optionally handle unexpected ranges, or just ignore
                    break;
            }

            $aging['total'] += $ar->outstanding_amount;
        }

        return $aging;
    }

    /**
     * Obtener estadísticas de cobranza
     */
    public function getCollectionStats(string $startDate, string $endDate): array
    {
        $payments = $this->customerPayment->byDateRange($startDate, $endDate)->get();

        return [
            'total_collected' => $payments->sum('amount'),
            'payment_count' => $payments->count(),
            'average_payment' => $payments->avg('amount'),
            'by_method' => $payments->groupBy('payment_method')->map->sum('amount'),
            'by_currency' => $payments->groupBy('currency')->map->sum('amount')
        ];
    }

    /**
     * Generar número de cuenta por cobrar
     */
    private function generateARNumber(): string
    {
        $lastAR = $this->accountReceivable->orderBy('ar_id', 'desc')->first();
        $nextNumber = $lastAR ? intval(substr($lastAR->ar_number, 3)) + 1 : 1;
        return 'AR-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener cuentas vencidas por cobrador
     */
    public function getOverdueByCollector(): Collection
    {
        return $this->accountReceivable->overdue()
            ->with(['collector', 'client'])
            ->get()
            ->groupBy('assigned_collector_id');
    }
    }
