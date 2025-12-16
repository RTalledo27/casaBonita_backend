<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use Modules\Security\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ForgotPasswordController extends Controller
{
    /**
     * Enviar enlace de recuperación de contraseña
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        // Verificar si el usuario existe
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No encontramos ningún usuario con este correo electrónico.']
            ]);
        }

        // Generar token único
        $token = Str::random(64);

        // Eliminar tokens anteriores para este email
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // Guardar nuevo token
        DB::table('password_reset_tokens')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => now()
        ]);

        // URL del frontend con el token
        $resetUrl = env('FRONTEND_URL', 'http://localhost:4200') . '/reset-password?token=' . $token . '&email=' . urlencode($request->email);

        // Log para debugging
        Log::info('Password Reset Request', [
            'email' => $request->email,
            'user_id' => $user->user_id ?? $user->id,
            'reset_url' => $resetUrl,
            'token' => substr($token, 0, 10) . '...'
        ]);

        // Enviar email
        try {
            if (config('clicklab.email_via_api')) {
                app(\App\Services\ClicklabMailer::class)->send($request->email, new ResetPasswordMail($user, $resetUrl, $token));
            } else {
                Mail::to($request->email)->send(new ResetPasswordMail($user, $resetUrl, $token));
            }
            
            Log::info('Password reset email sent successfully', [
                'email' => $request->email
            ]);
            
            return response()->json([
                'message' => 'Te hemos enviado un correo electrónico con las instrucciones para restablecer tu contraseña.',
                'debug_url' => env('APP_DEBUG') ? $resetUrl : null // Solo en desarrollo
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'email' => $request->email,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'Error al enviar el correo electrónico. Por favor, inténtalo de nuevo más tarde.',
                'error' => env('APP_DEBUG') ? $e->getMessage() : null,
                'debug_url' => env('APP_DEBUG') ? $resetUrl : null
            ], 500);
        }
    }
}
