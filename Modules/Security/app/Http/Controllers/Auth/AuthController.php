<?php

namespace Modules\Security\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\Security\Models\User;
use Modules\Security\Transformers\UserResource;

class AuthController extends Controller
{


    public function __construct()
    {
        // Middleware para proteger las rutas de autenticación
        // Solo el login es accesible sin autenticación
        
        $this->middleware('auth:sanctum')->except(['login']);
    }

    /**
     * Login de usuario
     *
     * @bodyParam email string required Correo del usuario. Example: admin@erp.com
     * @bodyParam password string required Contraseña. Example: Secret@123
     *
     * @response 200 {
     *   "token": "your_token",
     *   "user": { "id": 1, "name": "Admin", "email": "admin@erp.com", ... }
     * }
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        // Buscamos el usuario por username
        $user = User::where('username', $credentials['username'])->first();

        // Verificamos password contra password_hash
        if (! $user || ! Hash::check($credentials['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'username' => ['Credenciales inválidas.'],
            ]);
        }

        // Actualizamos el último login
        $user->update(['last_login_at' => now()]);

        // Registrar actividad de login
        UserActivityLog::log(
            $user->user_id,
            UserActivityLog::ACTION_LOGIN,
            'Sesión iniciada desde ' . $request->userAgent()
        );

        // Iniciar sesión automática de tracking
        $session = UserSession::startSession(
            $user->user_id,
            UserSession::TYPE_AUTO,
            $request->ip(),
            $request->userAgent()
        );

        // Generamos token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => new UserResource($user),
            'must_change_password' => $user->must_change_password,
            'session' => [
                'session_id' => $session->session_id,
                'started_at' => $session->started_at->toIso8601String(),
            ],
        ], 200);
    }


    /**
     * Cerrar sesión actual (opcional)
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Finalizar sesión activa de tracking
        $activeSession = UserSession::getActiveSession($user->user_id);
        if ($activeSession) {
            $activeSession->endSession();
        }
        
        // Registrar actividad de logout
        UserActivityLog::log(
            $user->user_id,
            UserActivityLog::ACTION_LOGOUT,
            'Sesión cerrada'
        );
        
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    /**
     * Obtener el usuario autenticado con permisos y roles
     */
    public function me(Request $request)
    {
        $user = $request->user();
          if (! $user) {
            return response()->json([
                'message' => 'Usuario no autenticado.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->load('roles', 'permissions');
        
        return response()->json([
            'user' => $user,
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'roles' => $user->getRoleNames()
        ]);
    }

    /**
     * Cambiar contraseña obligatorio (primer login)
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json([
                'message' => 'La contraseña actual es incorrecta.'
            ], 422);
        }

        // Actualizar contraseña
        $user->update([
            'password_hash' => Hash::make($request->new_password),
            'must_change_password' => false,
            'password_changed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada correctamente.',
            'must_change_password' => false,
        ]);
    }


    /**
     * Display a listing of the resource.
     */
   /* public function index()
    {
        return view('security::index');
    }*/

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('security::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('security::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('security::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
