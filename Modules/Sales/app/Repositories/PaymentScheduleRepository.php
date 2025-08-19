<?php

namespace Modules\Sales\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Sales\Models\PaymentSchedule;

class PaymentScheduleRepository
{
    public function paginate(array $relations = ['contract', 'payments'], int $perPage = 15, array $filters = []): LengthAwarePaginator
    {
        $query = PaymentSchedule::with($relations);
        
        \Log::info('🔍 PaymentScheduleRepository::paginate - Iniciando consulta:', [
            'filters' => $filters,
            'relations' => $relations
        ]);
        
        // Aplicar filtro de búsqueda por nombre de cliente
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            \Log::info('🔍 Aplicando filtro de búsqueda:', ['search' => $search]);
            
            $query->whereHas('contract.reservation.client', function ($q) use ($search) {
                $q->where('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%")
                  ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$search}%"]);
            });
        }
        
        // Aplicar filtro por estado si existe
        if (!empty($filters['status'])) {
            \Log::info('🔍 Aplicando filtro de estado:', ['status' => $filters['status']]);
            $query->where('status', $filters['status']);
        }
        
        // Aplicar filtro por rango de fechas si existe
        if (!empty($filters['from_date'])) {
            \Log::info('🔍 Aplicando filtro fecha desde:', ['from_date' => $filters['from_date']]);
            $query->where('due_date', '>=', $filters['from_date']);
        }
        
        if (!empty($filters['to_date'])) {
            \Log::info('🔍 Aplicando filtro fecha hasta:', ['to_date' => $filters['to_date']]);
            $query->where('due_date', '<=', $filters['to_date']);
        }
        
        // Log de la consulta SQL generada
        \Log::info('🔍 SQL Query generada:', ['sql' => $query->toSql(), 'bindings' => $query->getBindings()]);
        
        $result = $query->paginate($perPage);
        \Log::info('🔍 Resultados obtenidos:', ['total' => $result->total(), 'count' => $result->count()]);
        
        return $result;
    }


    public function find($id): ?PaymentSchedule
    {
        return PaymentSchedule::find($id);
    }
    
    public function create(array $data): PaymentSchedule
    {
        $schedule = PaymentSchedule::create($data);
        return $schedule->load(['contract', 'payments']);
    }

    public function update(PaymentSchedule $schedule, array $data): PaymentSchedule
    {
        $schedule->update($data);
        return $schedule->load(['contract', 'payments']);
    }

    public function delete(PaymentSchedule $schedule): void
    {
        $schedule->delete();
    }
}
