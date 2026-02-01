<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Models\Contract;
use Modules\Collections\app\Models\Followup;
use Carbon\Carbon;

class SegmentsController extends Controller
{
    public function preventive(Request $request)
    {
        $window = (int)($request->get('window', 15));
        $today = Carbon::now()->startOfDay();
        $limit = $today->copy()->addDays($window);

        // Obtener contratos con cuotas pendientes próximas a vencer
        $schedules = PaymentSchedule::with(['contract.reservation.client','contract.reservation.lot.manzana','contract.lot.manzana'])
            ->where('status', 'pendiente')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $limit)
            ->get();

        // Obtener todos los seguimientos preventivos existentes
        $existingFollowups = Followup::where('segment', 'preventivo')
            ->orWhere(function($q) {
                $q->where('overdue_installments', 0)
                  ->where('pending_installments', '>', 0);
            })
            ->get()
            ->keyBy('contract_id');

        $data = collect();

        // Mapear payment_schedules (1 fila por contrato: próxima cuota)
        $schedules->groupBy('contract_id')->each(function($group) use (&$data, $today, $existingFollowups) {
            $s = $group->sortBy('due_date')->first();
            $c = $s?->contract;
            if (!$c) return;
            
            $lot = $c->getLot();
            $manzana = $c->getManzanaName();
            $client = $c->getClient();
            $daysUntilDue = $s?->due_date ? Carbon::parse($s->due_date)->diffInDays($today, false) : 0;
            
            // Verificar si ya existe seguimiento para este contrato
            $followup = $existingFollowups->get($c->contract_id);
            
            $data->push([
                'contract_id' => $c->contract_id,
                'sale_code' => $c->contract_number,
                'client_id' => $client?->client_id,
                'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null,
                'phone1' => $client?->phone ?? $client?->mobile_phone ?? null,
                'phone2' => $client?->mobile_phone ?? null,
                'email' => $client?->email ?? null,
                'due_date' => $s->due_date,
                'monthly_quota' => $s->amount,
                'upcoming_installments' => (int) $group->count(),
                'upcoming_amount' => (float) $group->sum('amount'),
                'days_until_due' => abs($daysUntilDue),
                'lot_id' => $lot?->lot_id,
                'lot' => ($manzana || $lot?->num_lot) ? sprintf('MZ-%s L-%s', $manzana ?: '-', $lot?->num_lot ?: '-') : ($lot?->external_code ?: null),
                'schedule_id' => $s->schedule_id,
                'installment_number' => $s->installment_number,
                'has_followup' => $followup !== null,
                'followup_id' => $followup?->followup_id,
                'management_status' => $followup?->management_status,
                'last_contact' => $followup?->contact_date,
                'commitment_date' => $followup?->commitment_date,
                'commitment_amount' => $followup?->commitment_amount,
                'commitment_status' => $followup?->commitment_status,
            ]);
        });

        // Agregar seguimientos que no tienen payment_schedule en la ventana pero existen en BD
        $contractIdsFromSchedules = $schedules->pluck('contract_id')->unique();
        $existingFollowups->each(function($followup) use (&$data, $contractIdsFromSchedules) {
            if (!$contractIdsFromSchedules->contains($followup->contract_id)) {
                $data->push([
                    'contract_id' => $followup->contract_id,
                    'sale_code' => $followup->sale_code,
                    'client_id' => $followup->client_id,
                    'client_name' => $followup->client_name,
                    'phone1' => $followup->phone1,
                    'phone2' => $followup->phone2,
                    'email' => $followup->email,
                    'due_date' => $followup->due_date,
                    'monthly_quota' => $followup->monthly_quota,
                    'upcoming_installments' => 0,
                    'upcoming_amount' => 0,
                    'days_until_due' => 0,
                    'lot_id' => $followup->lot_id,
                    'lot' => $followup->lot,
                    'schedule_id' => null,
                    'installment_number' => null,
                    'has_followup' => true,
                    'followup_id' => $followup->followup_id,
                    'management_status' => $followup->management_status,
                    'last_contact' => $followup->contact_date,
                    'commitment_date' => $followup->commitment_date,
                    'commitment_amount' => $followup->commitment_amount,
                    'commitment_status' => $followup->commitment_status,
                ]);
            }
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function mora(Request $request)
    {
        $tramo = $request->get('tramo', '1');
        $today = Carbon::now()->startOfDay();
        
        // Obtener contratos con cuotas vencidas
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

        // Obtener todos los seguimientos en mora existentes
        $existingFollowups = Followup::where('segment', 'mora')
            ->orWhere('overdue_installments', '>', 0)
            ->get()
            ->keyBy('contract_id');

        $data = collect();

        // Mapear payment_schedules (1 fila por contrato)
        $filtered->groupBy('contract_id')->each(function($group) use (&$data, $today, $existingFollowups) {
            $s = $group->sortBy('due_date')->first();
            $c = $s?->contract;
            if (!$c) return;
            
            $lot = $c->getLot();
            $manzana = $c->getManzanaName();
            $client = $c->getClient();
            $days = $s?->due_date ? Carbon::parse($s->due_date)->diffInDays($today) : 0;
            
            $overdueCount = (int) $group->count();
            $overdueAmount = (float) $group->sum('amount');
            
            // Verificar si ya existe seguimiento para este contrato
            $followup = $existingFollowups->get($c->contract_id);
            
            $data->push([
                'contract_id' => $c->contract_id,
                'sale_code' => $c->contract_number,
                'client_id' => $client?->client_id,
                'client_name' => $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null,
                'phone1' => $client?->phone ?? $client?->mobile_phone ?? null,
                'phone2' => $client?->mobile_phone ?? null,
                'email' => $client?->email ?? null,
                'due_date' => $s->due_date,
                'monthly_quota' => $s->amount,
                'days_overdue' => $days,
                'overdue_installments' => $overdueCount,
                'overdue_amount' => $overdueAmount,
                'lot_id' => $lot?->lot_id,
                'lot' => ($manzana || $lot?->num_lot) ? sprintf('MZ-%s L-%s', $manzana ?: '-', $lot?->num_lot ?: '-') : ($lot?->external_code ?: null),
                'schedule_id' => $s->schedule_id,
                'installment_number' => $s->installment_number,
                'has_followup' => $followup !== null,
                'followup_id' => $followup?->followup_id,
                'management_status' => $followup?->management_status,
                'last_contact' => $followup?->contact_date,
                'commitment_date' => $followup?->commitment_date,
                'commitment_amount' => $followup?->commitment_amount,
                'commitment_status' => $followup?->commitment_status,
            ]);
        });

        // Agregar seguimientos que no tienen payment_schedule vencido pero existen en BD
        $contractIdsFromSchedules = $filtered->pluck('contract_id')->unique();
        $existingFollowups->each(function($followup) use (&$data, $contractIdsFromSchedules, $tramo) {
            if (!$contractIdsFromSchedules->contains($followup->contract_id)) {
                // Verificar que el seguimiento corresponde al tramo solicitado
                $matchesTramo = match($tramo) {
                    '1' => $followup->tramo === '1-30',
                    '2' => $followup->tramo === '31-60',
                    '3' => $followup->tramo === '61+',
                    default => true,
                };
                
                if ($matchesTramo) {
                    $data->push([
                        'contract_id' => $followup->contract_id,
                        'sale_code' => $followup->sale_code,
                        'client_id' => $followup->client_id,
                        'client_name' => $followup->client_name,
                        'phone1' => $followup->phone1,
                        'phone2' => $followup->phone2,
                        'email' => $followup->email,
                        'due_date' => $followup->due_date,
                        'monthly_quota' => $followup->monthly_quota,
                        'days_overdue' => 0,
                        'overdue_installments' => $followup->overdue_installments,
                        'overdue_amount' => (float) ($followup->pending_amount ?? 0),
                        'lot_id' => $followup->lot_id,
                        'lot' => $followup->lot,
                        'schedule_id' => null,
                        'installment_number' => null,
                        'has_followup' => true,
                        'followup_id' => $followup->followup_id,
                        'management_status' => $followup->management_status,
                        'last_contact' => $followup->contact_date,
                        'commitment_date' => $followup->commitment_date,
                        'commitment_amount' => $followup->commitment_amount,
                        'commitment_status' => $followup->commitment_status,
                    ]);
                }
            }
        });

        return response()->json(['success' => true, 'data' => $data]);
    }
}
