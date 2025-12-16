<?php

namespace Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Modules\Collections\Models\PaymentSchedule;
use App\Mail\InstallmentReminderMail;
use App\Mail\CustomCollectionsMail;
use Modules\Collections\Models\CollectionMessageLog;

class NotificationsController extends Controller
{
    public function sendScheduleReminder(Request $request, $schedule_id)
    {
        $schedule = PaymentSchedule::with(['contract.reservation.client', 'contract.client'])
            ->findOrFail($schedule_id);

        $client = method_exists($schedule->contract, 'getClient')
            ? $schedule->contract->getClient()
            : ($schedule->contract->client ?? $schedule->contract->reservation->client ?? null);

        $email = $request->input('email') ?: ($client?->email);

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene correo registrado',
                'data' => null
            ], 400);
        }

        $data = [
            'client_name' => $client->full_name ?? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
            'contract_number' => $schedule->contract->contract_number ?? null,
            'installment_number' => $schedule->installment_number,
            'notes' => $schedule->notes,
            'due_date' => $schedule->due_date,
            'amount' => $schedule->amount,
            'status' => $schedule->status,
            'payment_link' => config('app.url')
        ];

        if (config('clicklab.email_via_api')) {
            app(\App\Services\ClicklabMailer::class)->send($email, new InstallmentReminderMail($data));
        } else {
            Mail::to($email)->send(new InstallmentReminderMail($data));
        }

        CollectionMessageLog::create([
            'contract_id' => $schedule->contract_id,
            'schedule_id' => $schedule->schedule_id,
            'client_id' => $client?->client_id,
            'recipient_email' => $email,
            'subject' => 'Aviso de próxima cuota a vencer',
            'content_html' => view('emails.installment-reminder', $data)->render(),
            'status' => 'sent',
            'sent_at' => now(),
            'meta' => [ 'type' => 'reminder' ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aviso enviado exitosamente',
            'data' => [
                'schedule_id' => $schedule->schedule_id,
                'email' => $email
            ]
        ]);
    }

    public function sendUpcomingReminders(Request $request)
    {
        $daysAhead = (int)($request->input('days_ahead', 7));
        $from = now()->startOfDay();
        $to = now()->addDays($daysAhead)->endOfDay();

        $schedules = PaymentSchedule::with(['contract.reservation.client', 'contract.client'])
            ->where('status', 'pendiente')
            ->whereBetween('due_date', [$from, $to])
            ->get();

        $sent = 0;
        $failed = 0;
        foreach ($schedules as $schedule) {
            $client = method_exists($schedule->contract, 'getClient')
                ? $schedule->contract->getClient()
                : ($schedule->contract->client ?? $schedule->contract->reservation->client ?? null);
            $email = $client?->email;
            if (!$email) { $failed++; continue; }
            $data = [
                'client_name' => $client->full_name ?? trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? '')),
                'contract_number' => $schedule->contract->contract_number ?? null,
                'installment_number' => $schedule->installment_number,
                'notes' => $schedule->notes,
                'due_date' => $schedule->due_date,
                'amount' => $schedule->amount,
                'status' => $schedule->status,
                'payment_link' => config('app.url')
            ];
            try {
                if (config('clicklab.email_via_api')) {
                    app(\App\Services\ClicklabMailer::class)->send($email, new InstallmentReminderMail($data));
                } else {
                    Mail::to($email)->send(new InstallmentReminderMail($data));
                }
                CollectionMessageLog::create([
                    'contract_id' => $schedule->contract_id,
                    'schedule_id' => $schedule->schedule_id,
                    'client_id' => $client?->client_id,
                    'recipient_email' => $email,
                    'subject' => 'Aviso de próxima cuota a vencer',
                    'content_html' => view('emails.installment-reminder', $data)->render(),
                    'status' => 'sent',
                    'sent_at' => now(),
                    'meta' => [ 'type' => 'reminder' ]
                ]);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Procesamiento de avisos completado',
            'data' => [
                'total' => $schedules->count(),
                'sent' => $sent,
                'failed' => $failed,
                'days_ahead' => $daysAhead
            ]
        ]);
    }

    public function sendCustomEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string|max:150',
            'html' => 'required|string'
        ]);

        if (config('clicklab.email_via_api')) {
            app(\App\Services\ClicklabMailer::class)->send(
                $request->input('email'),
                new CustomCollectionsMail($request->input('subject'), $request->input('html'))
            );
        } else {
            Mail::to($request->input('email'))
                ->send(new CustomCollectionsMail($request->input('subject'), $request->input('html')));
        }

        CollectionMessageLog::create([
            'recipient_email' => $request->input('email'),
            'subject' => $request->input('subject'),
            'content_html' => $request->input('html'),
            'status' => 'sent',
            'sent_at' => now(),
            'meta' => [ 'type' => 'custom' ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Correo enviado exitosamente',
            'data' => [ 'email' => $request->input('email') ]
        ]);
    }

    public function sendCustomEmailForSchedule(Request $request, $schedule_id)
    {
        $request->validate([
            'subject' => 'required|string|max:150',
            'html' => 'required|string'
        ]);

        $schedule = PaymentSchedule::with(['contract.reservation.client', 'contract.client'])
            ->findOrFail($schedule_id);

        $client = method_exists($schedule->contract, 'getClient')
            ? $schedule->contract->getClient()
            : ($schedule->contract->client ?? $schedule->contract->reservation->client ?? null);

        $email = $client?->email;

        if (!$email) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene correo registrado',
                'data' => null
            ], 400);
        }

        if (config('clicklab.email_via_api')) {
            app(\App\Services\ClicklabMailer::class)->send($email, new CustomCollectionsMail($request->input('subject'), $request->input('html')));
        } else {
            Mail::to($email)->send(new CustomCollectionsMail($request->input('subject'), $request->input('html')));
        }

        CollectionMessageLog::create([
            'contract_id' => $schedule->contract_id,
            'schedule_id' => $schedule->schedule_id,
            'client_id' => $client?->client_id,
            'recipient_email' => $email,
            'subject' => $request->input('subject'),
            'content_html' => $request->input('html'),
            'status' => 'sent',
            'sent_at' => now(),
            'meta' => [ 'type' => 'custom' ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Correo personalizado enviado exitosamente',
            'data' => [ 'schedule_id' => $schedule_id, 'email' => $email ]
        ]);
    }

    public function confirmReception(Request $request, $log_id)
    {
        $log = CollectionMessageLog::findOrFail($log_id);
        $log->update([
            'status' => 'delivered',
            'delivered_at' => now()
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Recepción confirmada',
            'data' => [ 'log_id' => $log_id ]
        ]);
    }
}
