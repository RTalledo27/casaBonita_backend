<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Models\Contract;
use Carbon\Carbon;

class SegmentsController extends Controller
{
    public function preventive(Request $request)
    {
        $window = (int)($request->get('window', 15));
        $today = Carbon::now()->startOfDay();
        $limit = $today->copy()->addDays($window);

        $schedules = PaymentSchedule::with(['contract.reservation.client','contract.reservation.lot.manzana','contract.lot.manzana'])
            ->where('status', 'pendiente')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $limit)
            ->get();

        $data = $schedules->map(function($s){
            $c = $s->contract;
            $lot = $c?->getLot();
            $manzana = $c?->getManzanaName();
            $client = $c?->getClient();
            return [
                'contract_id' => $c?->contract_id,
                'sale_code' => $c?->contract_number,
                'client_id' => $client?->client_id,
                'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null,
                'due_date' => $s->due_date,
                'monthly_quota' => $s->amount,
                'lot_id' => $lot?->lot_id,
                'lot' => ($manzana || $lot?->num_lot) ? sprintf('MZ-%s L-%s', $manzana ?: '-', $lot?->num_lot ?: '-') : ($lot?->external_code ?: null),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function mora(Request $request)
    {
        $tramo = $request->get('tramo', '1');
        $today = Carbon::now()->startOfDay();
        $schedules = PaymentSchedule::with(['contract.reservation.client','contract.reservation.lot.manzana','contract.lot.manzana'])
            ->where('status', 'pendiente')
            ->whereDate('due_date', '<', $today)
            ->get();

        $filtered = $schedules->filter(function($s) use ($today, $tramo) {
            $days = Carbon::parse($s->due_date)->diffInDays($today);
            return match($tramo) {
                '1' => $days >= 1 && $days <= 30,
                '2' => $days >= 31 && $days <= 60,
                '3' => $days >= 61,
                default => true,
            };
        });

        $data = $filtered->map(function($s) use ($today){
            $c = $s->contract;
            $lot = $c?->getLot();
            $manzana = $c?->getManzanaName();
            $client = $c?->getClient();
            $days = Carbon::parse($s->due_date)->diffInDays($today);
            return [
                'contract_id' => $c?->contract_id,
                'sale_code' => $c?->contract_number,
                'client_id' => $client?->client_id,
                'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null,
                'due_date' => $s->due_date,
                'monthly_quota' => $s->amount,
                'days_overdue' => $days,
                'lot_id' => $lot?->lot_id,
                'lot' => ($manzana || $lot?->num_lot) ? sprintf('MZ-%s L-%s', $manzana ?: '-', $lot?->num_lot ?: '-') : ($lot?->external_code ?: null),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}

