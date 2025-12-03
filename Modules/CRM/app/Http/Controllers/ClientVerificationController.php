<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\CRM\Models\Client;
use Modules\CRM\Models\ClientVerification;
use App\Mail\ClientVerificationMail;
use App\Services\SmsService;

class ClientVerificationController extends Controller
{
    public function requestVerification(Request $request, $client_id)
    {
        $request->validate([
            'type' => 'required|in:email,phone',
            'value' => 'required|string|max:120'
        ]);

        $client = Client::findOrFail($client_id);
        $code = (string) random_int(100000, 999999);
        $expires = now()->addMinutes(15);

        $verification = ClientVerification::create([
            'client_id' => $client->client_id,
            'type' => $request->input('type'),
            'target_value' => $request->input('value'),
            'code' => $code,
            'expires_at' => $expires,
            'status' => 'pending'
        ]);

        $deliveryTo = null;
        if ($request->input('type') === 'phone') {
            $sent = app(SmsService::class)->send($request->input('value'), "Código de verificación: {$code}");
            if ($sent) {
                $deliveryTo = $request->input('value');
            } elseif ($client->email) {
                $data = [
                    'client_name' => $client->full_name,
                    'type' => 'phone',
                    'code' => $code,
                    'expires_at' => $expires->format('Y-m-d H:i')
                ];
                Mail::to($client->email)->send(new ClientVerificationMail($data));
                $deliveryTo = $client->email;
            }
        } else {
            $data = [
                'client_name' => $client->full_name,
                'type' => 'email',
                'code' => $code,
                'expires_at' => $expires->format('Y-m-d H:i')
            ];
            Mail::to($request->input('value'))->send(new ClientVerificationMail($data));
            $deliveryTo = $request->input('value');
        }

        return response()->json([
            'success' => true,
            'message' => 'Verificación iniciada',
            'data' => [
                'verification_id' => $verification->id,
                'expires_at' => $expires,
                'delivery_to' => $deliveryTo
            ]
        ]);
    }

    public function requestAnon(Request $request)
    {
        $request->validate([
            'type' => 'required|in:email,phone',
            'value' => 'required|string|max:120',
            'relay_email' => 'nullable|email'
        ]);

        $code = (string) random_int(100000, 999999);
        $expires = now()->addMinutes(15);

        $verification = ClientVerification::create([
            'client_id' => 0,
            'type' => $request->input('type'),
            'target_value' => $request->input('value'),
            'code' => $code,
            'expires_at' => $expires,
            'status' => 'pending'
        ]);

        $deliveryTo = null;
        if ($request->input('type') === 'phone') {
            $sent = app(SmsService::class)->send($request->input('value'), "Código de verificación: {$code}");
            if ($sent) {
                $deliveryTo = $request->input('value');
            } elseif ($request->input('relay_email')) {
                $data = [
                    'client_name' => 'Usuario',
                    'type' => 'phone',
                    'code' => $code,
                    'expires_at' => $expires->format('Y-m-d H:i')
                ];
                Mail::to($request->input('relay_email'))->send(new ClientVerificationMail($data));
                $deliveryTo = $request->input('relay_email');
            }
        } else {
            $data = [
                'client_name' => 'Usuario',
                'type' => 'email',
                'code' => $code,
                'expires_at' => $expires->format('Y-m-d H:i')
            ];
            Mail::to($request->input('value'))->send(new ClientVerificationMail($data));
            $deliveryTo = $request->input('value');
        }

        return response()->json([
            'success' => true,
            'message' => 'Verificación iniciada',
            'data' => [
                'verification_id' => $verification->id,
                'expires_at' => $expires,
                'delivery_to' => $deliveryTo
            ]
        ]);
    }

    public function confirmAnon(Request $request)
    {
        $request->validate([
            'verification_id' => 'required|integer',
            'code' => 'required|string'
        ]);

        $verification = ClientVerification::findOrFail($request->input('verification_id'));

        if ($verification->status !== 'pending' || now()->greaterThan($verification->expires_at)) {
            $verification->update(['status' => 'expired']);
            return response()->json([
                'success' => false,
                'message' => 'Código expirado',
                'data' => null
            ], 400);
        }

        $verification->increment('attempts');

        if (!hash_equals($verification->code, $request->input('code'))) {
            return response()->json([
                'success' => false,
                'message' => 'Código inválido',
                'data' => null
            ], 400);
        }

        $verification->update([
            'status' => 'verified',
            'verified_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Información verificada',
            'data' => [
                'type' => $verification->type,
                'value' => $verification->target_value
            ]
        ]);
    }

    public function confirmVerification(Request $request, $client_id)
    {
        $request->validate([
            'verification_id' => 'required|integer',
            'code' => 'required|string'
        ]);

        $client = Client::findOrFail($client_id);
        $verification = ClientVerification::where('id', $request->input('verification_id'))
            ->where('client_id', $client->client_id)
            ->firstOrFail();

        if ($verification->status !== 'pending' || now()->greaterThan($verification->expires_at)) {
            $verification->update(['status' => 'expired']);
            return response()->json([
                'success' => false,
                'message' => 'Código expirado',
                'data' => null
            ], 400);
        }

        $verification->increment('attempts');

        if (!hash_equals($verification->code, $request->input('code'))) {
            return response()->json([
                'success' => false,
                'message' => 'Código inválido',
                'data' => null
            ], 400);
        }

        if ($verification->type === 'email') {
            $client->email = $verification->target_value;
        } else {
            $client->primary_phone = $verification->target_value;
        }
        $client->save();

        $verification->update([
            'status' => 'verified',
            'verified_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Información verificada y actualizada',
            'data' => [
                'client_id' => $client->client_id,
                'type' => $verification->type,
                'value' => $verification->target_value
            ]
        ]);
    }

    public function resendVerification(Request $request, $client_id)
    {
        $request->validate([
            'verification_id' => 'required|integer'
        ]);

        $client = Client::findOrFail($client_id);
        $verification = ClientVerification::where('id', $request->input('verification_id'))
            ->where('client_id', $client->client_id)
            ->firstOrFail();

        if ($verification->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'No se puede reenviar un código no pendiente',
                'data' => null
            ], 400);
        }

        $verification->update(['expires_at' => now()->addMinutes(15)]);
        $recipient = $verification->type === 'email' ? $verification->target_value : ($client->email);
        if ($recipient) {
            $data = [
                'client_name' => $client->full_name,
                'type' => $verification->type,
                'code' => $verification->code,
                'expires_at' => $verification->expires_at->format('Y-m-d H:i')
            ];
            Mail::to($recipient)->send(new ClientVerificationMail($data));
        }

        return response()->json([
            'success' => true,
            'message' => 'Código reenviado',
            'data' => [
                'verification_id' => $verification->id,
                'expires_at' => $verification->expires_at
            ]
        ]);
    }
}
