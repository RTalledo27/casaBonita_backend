<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PaymentScheduleRepository
{
    /**
     * Get payment schedules with filters
     *
     * @param array $filters
     * @return Builder
     */
    public function getFilteredSchedules(array $filters = []): Builder
    {
        $query = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->join('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->join('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->select([
                'ps.schedule_id',
                'ps.contract_id',
                'ps.installment_number',
                'ps.due_date',
                'ps.amount',
                'ps.status',
                'ps.payment_date',
                'ps.amount_paid',
                'ps.payment_method',
                'ps.notes',
                'ps.created_at',
                'ps.updated_at',
                DB::raw('CONCAT(cl.first_name, " ", cl.last_name) as client_name'),
                'cl.first_name',
                'cl.last_name',
                'cl.doc_number',
                'cl.primary_phone',
                'l.lot_number',
                'l.block',
                'l.area',
                'l.price as lot_price'
            ]);

        // Apply search filter for client name
        if (!empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('cl.first_name', 'LIKE', $searchTerm)
                  ->orWhere('cl.last_name', 'LIKE', $searchTerm)
                  ->orWhere(DB::raw('CONCAT(cl.first_name, " ", cl.last_name)'), 'LIKE', $searchTerm)
                  ->orWhere('cl.doc_number', 'LIKE', $searchTerm)
                  ->orWhere('l.lot_number', 'LIKE', $searchTerm);
            });
        }

        // Apply status filter
        if (!empty($filters['status'])) {
            $query->where('ps.status', $filters['status']);
        }

        // Apply date range filters
        if (!empty($filters['date_from'])) {
            $query->where('ps.due_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('ps.due_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('ps.due_date', 'asc');
    }
}