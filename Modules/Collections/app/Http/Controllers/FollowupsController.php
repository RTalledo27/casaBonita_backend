<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Collections\app\Models\Followup;
use Modules\Collections\app\Models\FollowupLog;
use Modules\Sales\Models\Contract;
use Modules\Sales\Models\PaymentSchedule;
use Modules\CRM\Models\Client;
use Modules\Inventory\Models\Lot;
use Carbon\Carbon;
use App\Services\ClicklabClient;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
                $f->contract_status = $f->contract_status ?: ($contract->status ?? null);
                $advisor = $contract->getAdvisor();
                if ($advisor) {
                    $f->advisor_id = $f->advisor_id ?: ($advisor->employee_id ?? null);
                    $f->advisor_name = $f->advisor_name ?: trim(($advisor->user->first_name ?? '') . ' ' . ($advisor->user->last_name ?? ''));
                }

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
            // Estado y asesor
            $data['contract_status'] = $contract->status ?? null;
            $advisor = $contract->getAdvisor();
            if ($advisor) {
                $data['advisor_id'] = $advisor->employee_id ?? null;
                $data['advisor_name'] = trim(($advisor->user->first_name ?? '') . ' ' . ($advisor->user->last_name ?? ''));
            }

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

    public function updateCommitment(Request $request, int $id)
    {
        $data = $request->validate([
            'commitment_date' => 'required|date',
            'commitment_amount' => 'required|numeric',
        ]);
        $followup = Followup::findOrFail($id);
        $followup->commitment_date = $data['commitment_date'];
        $followup->commitment_amount = $data['commitment_amount'];
        $followup->save();
        return response()->json(['success' => true, 'data' => $followup]);
    }

    /**
     * Ejecutar acción rápida de comunicación y registrar automáticamente
     * Acepta followup_id o crea uno automáticamente desde contract_id
     * 
     * @param Request $request
     * @param int $id - Puede ser followup_id o contract_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function quickAction(Request $request, int $id)
    {
        $data = $request->validate([
            'channel' => 'required|in:whatsapp,sms,email,call,letter',
            'message' => 'nullable|string',
            'subject' => 'nullable|string',
            'result' => 'nullable|string',
            'notes' => 'nullable|string',
            'use_contract_id' => 'nullable|boolean', // Indica si $id es contract_id
        ]);

        // Intentar obtener followup existente o crear uno temporal desde contrato
        $followup = $this->getOrCreateFollowup($id, $data['use_contract_id'] ?? false);
        
        if (!$followup) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el registro de seguimiento ni el contrato'
            ], 404);
        }

        $channel = $data['channel'];
        $message = $data['message'] ?? $this->getDefaultMessage($followup, $channel);
        $success = false;
        $errorMessage = null;

        try {
            switch ($channel) {
                case 'whatsapp':
                    $success = $this->sendWhatsApp($followup, $message);
                    break;

                case 'sms':
                    $success = $this->sendSMS($followup, $message);
                    break;

                case 'email':
                    $subject = $data['subject'] ?? 'Recordatorio de Pago - Casa Bonita';
                    $success = $this->sendEmail($followup, $subject, $message);
                    break;

                case 'call':
                case 'letter':
                    // Para llamadas y cartas, solo registramos el log
                    $success = true;
                    break;
            }
        } catch (\Exception $e) {
            Log::error("Error en acción rápida {$channel} para followup {$id}: " . $e->getMessage());
            $errorMessage = $e->getMessage();
            $success = false;
        }

        // Registrar en followup_logs
        $logData = [
            'followup_id' => $followup->followup_id,
            'client_id' => $followup->client_id,
            'employee_id' => auth()->id() ?? $followup->assigned_employee_id,
            'channel' => $channel,
            'result' => $success ? ($data['result'] ?? 'sent') : 'failed',
            'notes' => $data['notes'] ?? ($success ? "Mensaje enviado exitosamente" : "Error: {$errorMessage}"),
            'logged_at' => now(),
        ];

        FollowupLog::create($logData);

        // Actualizar última fecha de contacto
        if ($success) {
            $followup->contact_date = now();
            $followup->channel = $channel;
            $followup->save();
        }

        return response()->json([
            'success' => $success,
            'message' => $success 
                ? "Acción de {$channel} ejecutada correctamente" 
                : "Error al ejecutar acción: {$errorMessage}",
            'data' => [
                'followup' => $followup,
                'log' => $logData
            ]
        ], $success ? 200 : 500);
    }

    /**
     * Enviar mensaje por WhatsApp usando ClicklabClient
     */
    private function sendWhatsApp(Followup $followup, string $message): bool
    {
        if (!$followup->phone1) {
            throw new \Exception('Cliente no tiene teléfono registrado');
        }

        $clicklab = app(ClicklabClient::class);
        $result = $clicklab->sendWhatsappText($followup->phone1, $message);
        
        if (!$result['ok']) {
            throw new \Exception('Error al enviar WhatsApp: ' . json_encode($result['body']));
        }

        return true;
    }

    /**
     * Enviar SMS usando SmsService
     */
    private function sendSMS(Followup $followup, string $message): bool
    {
        if (!$followup->phone1) {
            throw new \Exception('Cliente no tiene teléfono registrado');
        }

        $smsService = app(SmsService::class);
        $sent = $smsService->send($followup->phone1, $message);
        
        if (!$sent) {
            throw new \Exception('Error al enviar SMS');
        }

        return true;
    }

    /**
     * Enviar email usando Mail de Laravel
     */
    private function sendEmail(Followup $followup, string $subject, string $message): bool
    {
        if (!$followup->email) {
            throw new \Exception('Cliente no tiene email registrado');
        }

        Mail::html($message, function ($mail) use ($followup, $subject) {
            $mail->to($followup->email, $followup->client_name)
                 ->subject($subject)
                 ->from(config('mail.from.address'), config('mail.from.name'));
        });

        return true;
    }

    /**
     * Generar mensaje predeterminado según el canal
     */
    private function getDefaultMessage(Followup $followup, string $channel): string
    {
        $clientName = $followup->client_name ?? 'Estimado cliente';
        $firstName = explode(' ', $clientName)[0];
        $amount = number_format($followup->monthly_quota ?? 0, 2);
        $dueDate = $followup->due_date ? Carbon::parse($followup->due_date)->format('d/m/Y') : 'próximamente';

        switch ($channel) {
            case 'whatsapp':
            case 'sms':
                return "Hola {$firstName}, te recordamos que tienes una cuota pendiente de S/ {$amount} con vencimiento el {$dueDate}. Para cualquier consulta, comunícate con nosotros. Gracias - Casa Bonita";

            case 'email':
                return "
                    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5;'>
                        <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px;'>
                            <h2 style='color: #4F46E5;'>Recordatorio de Pago</h2>
                            <p>Estimado/a <strong>{$clientName}</strong>,</p>
                            <p>Le recordamos que tiene una cuota pendiente con los siguientes detalles:</p>
                            <ul>
                                <li><strong>Monto:</strong> S/ {$amount}</li>
                                <li><strong>Fecha de vencimiento:</strong> {$dueDate}</li>
                                <li><strong>Código de venta:</strong> {$followup->sale_code}</li>
                            </ul>
                            <p>Para realizar su pago o coordinar cualquier consulta, no dude en comunicarse con nosotros.</p>
                            <p style='margin-top: 30px;'>Atentamente,<br><strong>Casa Bonita</strong></p>
                        </div>
                    </div>
                ";

            default:
                return "Recordatorio de pago - Cuota: S/ {$amount} - Vencimiento: {$dueDate}";
        }
    }

    /**
     * Obtener followup existente o crear datos temporales desde contrato
     */
    private function getOrCreateFollowup(int $id, bool $useContractId = false): ?Followup
    {
        // Intentar como followup_id primero
        if (!$useContractId) {
            $followup = Followup::find($id);
            if ($followup) {
                return $followup;
            }
        }

        // Si no existe como followup, buscar como contract_id y crear objeto temporal
        $contract = Contract::find($id);
        if (!$contract) {
            return null;
        }

        // Crear objeto Followup temporal (no guardado en BD) para enviar mensajes
        $client = $contract->getClient();
        $followup = new Followup();
        $followup->followup_id = 0; // Temporal
        $followup->contract_id = $contract->contract_id;
        $followup->client_id = $contract->client_id;
        $followup->client_name = $client ? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')) : null;
        $followup->phone1 = $client?->primary_phone;
        $followup->phone2 = $client?->secondary_phone;
        $followup->email = $client?->email;
        $followup->sale_code = $contract->contract_number;

        // Obtener próximo vencimiento
        $schedules = PaymentSchedule::where('contract_id', $contract->contract_id)->get();
        $today = Carbon::now()->startOfDay();
        $pending = $schedules->where('status', 'pendiente');
        $nextDue = $pending->filter(function($s) use ($today) { 
            return $s->due_date && Carbon::parse($s->due_date)->gte($today); 
        })->sortBy('due_date')->first();

        $followup->due_date = $nextDue->due_date ?? null;
        $followup->monthly_quota = $nextDue->amount ?? null;

        return $followup;
    }
}
