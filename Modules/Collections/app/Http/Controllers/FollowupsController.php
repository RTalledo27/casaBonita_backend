<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Collections\app\Models\Followup;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;
use Modules\CRM\Models\Client;
use Modules\Inventory\Models\Lot;
use Carbon\Carbon;

class FollowupsController extends Controller
{
    public function index(Request $request)
    {
        $query = Followup::query();
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }
        if ($request->filled('assigned_employee_id')) {
            $query->where('assigned_employee_id', $request->integer('assigned_employee_id'));
        }
        $paginator = $query->orderByDesc('followup_id')->paginate(20);

        // Enriquecer en respuesta para registros antiguos incompletos
        $collection = $paginator->getCollection()->map(function ($f) {
            // Cliente
            if (!$f->client_name || !$f->dni || !$f->phone1 || !$f->email) {
                $client = Client::find($f->client_id);
                if ($client) {
                    $f->client_name = $f->client_name ?: trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
                    $f->dni = $f->dni ?: ($client->doc_number ?? null);
                    $f->phone1 = $f->phone1 ?: ($client->primary_phone ?? null);
                    $f->phone2 = $f->phone2 ?: ($client->secondary_phone ?? null);
                    $f->email = $f->email ?: ($client->email ?? null);
                    $addr = $client->addresses()->orderByDesc('address_id')->first();
                    if ($addr) {
                        $f->address = $f->address ?: ($addr->line1 ?? null);
                        $f->district = $f->district ?: ($addr->city ?? null);
                        $f->province = $f->province ?: ($addr->state ?? null);
                        $f->department = $f->department ?: ($addr->state ?? null);
                    }
                }
            }

            // Contrato / lote / cronogramas
            $contract = $f->contract_id ? Contract::find($f->contract_id) : Contract::where('client_id', $f->client_id)->orderByDesc('contract_id')->first();
            $schedules = collect();
            if ($contract) {
                $f->contract_id = $contract->contract_id;
                $f->sale_code = $f->sale_code ?: ($contract->contract_number ?? $f->sale_code);
                $f->lot_id = $f->lot_id ?: ($contract->lot_id ?? null);

                // Lote
                if (!$f->lot) {
                    $lot = optional(optional($contract->reservation)->lot);
                    $manzanaName = optional($lot->manzana)->name;
                    if ($lot) {
                        $f->lot = sprintf('MZ-%s L-%s', $manzanaName ?: $lot->manzana_id, $lot->num_lot);
                    }
                }

                // Cronogramas
                $schedules = PaymentSchedule::where('contract_id', $contract->contract_id)->get();
                $today = Carbon::now()->startOfDay();
                $paid = $schedules->where('status', 'pagado');
                $pending = $schedules->where('status', 'pendiente');
                $overdue = $pending->filter(function($s) use ($today) { return $s->due_date && Carbon::parse($s->due_date)->lt($today); });
                $nextDue = $pending->filter(function($s) use ($today) { return $s->due_date && Carbon::parse($s->due_date)->gte($today); })->sortBy('due_date')->first();

                $f->due_date = $f->due_date ?: ($nextDue->due_date ?? null);
                $f->monthly_quota = $f->monthly_quota ?: ($nextDue->amount ?? null);
                $f->sale_price = $f->sale_price ?: ($contract->total_price ?? null);

                $f->paid_installments = $f->paid_installments ?: $paid->count();
                $f->pending_installments = $f->pending_installments ?: $pending->count();
                $f->total_installments = $f->total_installments ?: $schedules->count();
                $f->overdue_installments = $f->overdue_installments ?: $overdue->count();
                $f->amount_paid = $f->amount_paid ?: $paid->sum(function($s){ return $s->amount_paid ?? $s->amount; });
                $f->amount_due = $f->amount_due ?: $pending->sum('amount');
                $f->pending_amount = $f->pending_amount ?: $overdue->sum('amount');
                // Datos del lote para tooltip
                $lot = $contract->getLot();
                if ($lot) {
                    $f->lot_id = $f->lot_id ?: $lot->lot_id;
                    $f->lot_area_m2 = $f->lot_area_m2 ?: ($lot->area_m2 ?? null);
                    $f->lot_status = $f->lot_status ?: ($lot->status ?? null);
                    if (!$f->lot) {
                        $manzanaName = $contract->getManzanaName();
                        $numLot = $lot?->num_lot;
                        $f->lot = sprintf('MZ-%s L-%s', $manzanaName ?: '-', $numLot ?: '-');
                    }
                }
            } else {
                // Lote desde reserva si no hay contrato
                if (!$f->lot) {
                    $reservation = \Modules\Sales\Models\Reservation::where('client_id', $f->client_id)->orderByDesc('reservation_id')->first();
                    if ($reservation && $reservation->lot) {
                        $lot = $reservation->lot;
                        $manzanaName = optional($lot->manzana)->name;
                        $f->lot = sprintf('MZ-%s L-%s', $manzanaName ?: $lot->manzana_id, $lot->num_lot);
                        $f->lot_id = $f->lot_id ?: $lot->lot_id;
                        $f->lot_area_m2 = $f->lot_area_m2 ?: ($lot->area_m2 ?? null);
                        $f->lot_status = $f->lot_status ?: ($lot->status ?? null);
                    }
                }
            }

            return $f;
        });

        $paginator->setCollection($collection);
        return response()->json(['success' => true, 'data' => $paginator]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|integer',
            'assigned_employee_id' => 'nullable|integer',
            'management_status' => 'nullable|string',
            'action_taken' => 'nullable|string',
            'management_result' => 'nullable|string',
            'management_notes' => 'nullable|string',
            'owner' => 'nullable|string',
            'contact_date' => 'nullable|date'
        ]);

        // Datos de cliente
        $client = Client::find($data['client_id']);
        if ($client) {
            $data['client_name'] = trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''));
            $data['dni'] = $client->doc_number ?? null;
            $data['phone1'] = $client->primary_phone ?? null;
            $data['phone2'] = $client->secondary_phone ?? null;
            $data['email'] = $client->email ?? null;
            $addr = $client->addresses()->orderByDesc('address_id')->first();
            if ($addr) {
                $data['address'] = $addr->line1 ?? null;
                $data['district'] = $addr->city ?? null;      // ej. PAITA
                $data['province'] = $addr->state ?? null;      // ej. PIURA
                $data['department'] = $addr->state ?? null;    // Mejor que 'PER'
            }
        }

        // Auto-enriquecer con contrato y cronogramas
        $contract = Contract::where('client_id', $data['client_id'])
            ->orderByDesc('contract_id')
            ->first();

        // Inicializar colecciones vacías para métricas
        $schedules = collect();
        $paid = collect();
        $pending = collect();
        $overdue = collect();

        if ($contract) {
            $data['contract_id'] = $contract->contract_id;
            $data['sale_code'] = $contract->contract_number ?? null;
            $data['lot_id'] = $contract->lot_id ?? null;

            $schedules = PaymentSchedule::where('contract_id', $contract->contract_id)->get();
            $today = Carbon::now()->startOfDay();
            $paid = $schedules->where('status', 'pagado');
            $pending = $schedules->where('status', 'pendiente');
            $overdue = $pending->filter(function($s) use ($today) { return $s->due_date && Carbon::parse($s->due_date)->lt($today); });
            $nextDue = $pending->filter(function($s) use ($today) { return $s->due_date && Carbon::parse($s->due_date)->gte($today); })->sortBy('due_date')->first();

            $data['due_date'] = $nextDue->due_date ?? null;
            $data['monthly_quota'] = $nextDue->amount ?? null;
            $data['sale_price'] = $contract->total_price ?? null;

            // Lote (manzana-num_lot)
            $lot = $contract->getLot();
            $manzanaName = $contract->getManzanaName();
            $numLot = $lot?->num_lot;
            if ($lot) {
                $data['lot_id'] = $lot->lot_id;
                $data['lot'] = sprintf('MZ-%s L-%s', $manzanaName ?: '-', $numLot ?: '-');
                $data['lot_area_m2'] = $lot->area_m2 ?? null;
                $data['lot_status'] = $lot->status ?? null;
            } elseif ($lot?->external_code) {
                $data['lot'] = $lot->external_code;
            }
        } else {
            // Fallback: usar última reserva del cliente para obtener el lote
            $reservation = \Modules\Sales\Models\Reservation::where('client_id', $data['client_id'])
                ->orderByDesc('reservation_id')
                ->first();
            if ($reservation && $reservation->lot) {
                $lot = $reservation->lot;
                $data['lot_id'] = $lot->lot_id;
                $manzanaName = $lot?->manzana?->name ?: $lot?->manzana_id;
                $numLot = $lot?->num_lot ?: '-';
                $data['lot'] = sprintf('MZ-%s L-%s', $manzanaName ?: '-', $numLot);
                $data['lot_area_m2'] = $lot->area_m2 ?? null;
                $data['lot_status'] = $lot->status ?? null;
            }
        }

        // Métricas de cronograma
        $data['paid_installments'] = $paid->count();
        $data['pending_installments'] = $pending->count();
        $data['total_installments'] = $schedules->count();
        $data['overdue_installments'] = $overdue->count();
        $data['amount_paid'] = $paid->sum(function($s){ return $s->amount_paid ?? $s->amount; });
        $data['amount_due'] = $pending->sum('amount');
        $data['pending_amount'] = $overdue->sum('amount');

        $followup = Followup::create($data);

        return response()->json(['success' => true, 'data' => $followup], 201);
    }

    public function update(Request $request, int $id)
    {
        $followup = Followup::findOrFail($id);
        $followup->fill($request->all());
        $followup->save();
        return response()->json(['success' => true, 'data' => $followup]);
    }
}
