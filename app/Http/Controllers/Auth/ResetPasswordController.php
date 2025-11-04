<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Modules\Security\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    /**
     * Restablecer contraseña del usuario
     */
    public function reset(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::min(8)
                ->letters()
                ->mixedCase()
                ->numbers()
                ->symbols()],
        ], [
            'password.required' => 'La contraseña es obligatoria.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.letters' => 'La contraseña debe contener al menos una letra.',
            'password.mixed' => 'La contraseña debe contener mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
            'password.symbols' => 'La contraseña debe contener al menos un símbolo.',
        ]);

        // Buscar el token en la base de datos
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$passwordReset) {
            throw ValidationException::withMessages([
                'token' => ['El token de recuperación es inválido o ha expirado.']
            ]);
        }

        // Verificar si el token no ha expirado (60 minutos)
        $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
        if ($createdAt->addHour()->isPast()) {
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();
                
            throw ValidationException::withMessages([
                'token' => ['El token de recuperación ha expirado. Por favor, solicita uno nuevo.']
            ]);
        }

        // Buscar al usuario
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['No se encontró ningún usuario con este correo electrónico.']
            ]);
        }

        // Actualizar la contraseña
        $user->password = Hash::make($request->password);
        $user->must_change_password = false; // Ya cambió su contraseña
        $user->save();

        // Eliminar el token usado
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'message' => 'Tu contraseña ha sido restablecida exitosamente. Ya puedes iniciar sesión con tu nueva contraseña.'
        ], 200);
    }

    /**
     * Verificar si un token es válido
     */
    public function verifyToken(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email'
        ]);

        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$passwordReset) {
            return response()->json([
                'valid' => false,
                'message' => 'El token es inválido.'
            ], 200);
        }

        // Verificar expiración
        $createdAt = \Carbon\Carbon::parse($passwordReset->created_at);
        if ($createdAt->addHour()->isPast()) {
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();
                
            return response()->json([
                'valid' => false,
                'message' => 'El token ha expirado.'
            ], 200);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Token válido.'
        ], 200);
    }
}
