<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Security\Http\Requests\StoreUserRequest;
use Modules\Security\Http\Requests\UpdateUserRequest;
use Modules\Security\Models\User;
use Modules\Security\Repositories\UserRepository;
use Modules\Security\Transformers\UserResource;
use Pusher\Pusher;
use Symfony\Component\HttpFoundation\Response;
use App\Mail\NewUserCredentialsMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\ClicklabClient;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{

    /**
     * 
     * INICIALIZACION PUSHER
     * 
     */

     private function pusherInstance(){
        return new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS' => true
            ]

        );
     }



    /**
     * @group Seguridad - Gestión de Usuarios
     *
     * Endpoints para administrar usuarios del sistema.
     */

     protected $repository;

    //CONSTRUCTOR
    public function __construct(UserRepository $repository)
    {
        $this->middleware('permission:security.users.index')->only(['index', 'show']);
        $this->middleware('permission:security.users.store')->only(['store']);
        $this->middleware('permission:security.users.update')->only(['update']);
        $this->middleware('permission:security.users.destroy')->only(['destroy']);
        $this->middleware('permission:security.users.change-password')->only(['changePassword']);
        $this->middleware('permission:security.users.toggle-status')->only(['toggleStatus']);
        $this->middleware('permission:security.users.update')->only(['syncRoles']);

        $this->repository = $repository;
    }


    /**
     * Listar usuarios
     *
     * Devuelve la lista paginada de usuarios con sus roles.
     *
     * @queryParam search string Buscar por nombre o correo. Example: admin
     * @queryParam sort_by string Campo para ordenar. Example: name
     * @queryParam sort_dir string Dirección (asc/desc). Example: desc
     * @queryParam per_page int Resultados por página. Example: 10
     *
     * @response 200
     */
    public function index(Request $request)
    {
        $filters = [
            'search' => $request->get('search'),
            'sort_by' => $request->get('sort_by', 'created_at'),
            'sort_dir' => $request->get('sort_dir', 'desc'),
            'per_page' => $request->get('per_page', 15)
        ];

        $users = $this->repository->paginate($filters);
        return UserResource::collection($users);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        }



    /**
     * Crear nuevo usuario
     *
     * @bodyParam name string required Nombre del usuario. Example: Juan Pérez
     * @bodyParam email string required Correo único. Example: juan@erp.com
     * @bodyParam password string required Contraseña. Example: secret123
     * @bodyParam role string required Rol a asignar. Example: admin
     *
     * @response 201
     */
    public function store(StoreUserRequest $request)
    {
        try {
            DB::beginTransaction();


            $data = $request->validated();
            $data['photo_profile'] = $request->file('photo_profile');
            $data['cv_file'] = $request->file('cv_file');

            $user = $this->repository->create($data);


            if ($request->has('roles')) {
                $user->syncRoles($request->input('roles'));
                // Limpiar el caché de permisos después de asignar roles
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            }

            $data['created_by'] = $request->user()->user_id;

            DB::commit();

            UserActivityLog::log(
                $request->user()->user_id,
                UserActivityLog::ACTION_SECURITY_USER_CREATED,
                'Usuario creado',
                [
                    'target_user_id' => $user->user_id,
                    'target_username' => $user->username,
                    'roles' => $user->getRoleNames()->toArray(),
                ]
            );

            try {
                $plainPassword = $request->input('password') ?: Str::random(12);
                if (!$request->input('password')) {
                    $user->password_hash = \Illuminate\Support\Facades\Hash::make($plainPassword);
                    $user->must_change_password = true;
                    $user->save();
                }
                $loginUrl = config('app.frontend_url') ?? env('FRONTEND_URL', 'http://localhost:4200');
                if (config('clicklab.email_via_api')) {
                    $html = view('emails.new-user-credentials', [
                        'user' => $user,
                        'temporaryPassword' => $plainPassword,
                        'loginUrl' => $loginUrl,
                    ])->render();
                    app(\App\Services\ClicklabClient::class)->sendEmail($user->email, 'Tus Credenciales de Acceso', $html);
                } else {
                    Mail::to($user->email)->send(new NewUserCredentialsMail($user, $plainPassword, $loginUrl));
                }
                if (config('clicklab.notify_on_user_create')) {
                    $channels = config('clicklab.channels', []);
                    $client = app(ClicklabClient::class);
                    $text = 'Bienvenido. Usuario: ' . $user->username . ' Clave: ' . $plainPassword . ' Ingreso: ' . $loginUrl;
                    if (in_array('sms', $channels) && $user->phone) {
                        $client->sendSms($user->phone, $text);
                    }
                    if (in_array('whatsapp', $channels) && $user->phone) {
                        $tpl = config('clicklab.wa_template_name');
                        $ns  = config('clicklab.wa_template_namespace');
                        $lang= config('clicklab.wa_template_language');
                        if ($tpl) {
                            $client->sendWhatsappTemplate($user->phone, $tpl, [$user->first_name, $user->username, $plainPassword, $loginUrl], $lang, $ns);
                        } else {
                            $client->sendWhatsappText($user->phone, $text);
                        }
                    }
                }
            } catch (\Throwable $mailError) {
                Log::error('Error enviando credenciales al crear usuario', [
                    'user_id' => $user->user_id,
                    'email' => $user->email,
                    'error' => $mailError->getMessage(),
                ]);
            }

            //PUSHER
            $pusher = $this->pusherInstance();
            $pusher->trigger('user-channel', 'created', [
                'user' => (new UserResource($user->fresh('roles')))->toArray($request)
            ]);


            return (new UserResource($user->fresh('roles')))
                ->response()
                ->setStatusCode(201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear usuario',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Ver usuario específico
     *
     * @urlParam id int required ID del usuario. Example: 2
     * @response 200
     */
    public function show(User $user)
    {
        $user->load('roles');

        return new UserResource($user);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('security::edit');
    }

    /**
     * Actualizar usuario
     *
     * @urlParam id int required ID del usuario. Example: 2
     * @bodyParam name string Nombre del usuario.
     * @bodyParam email string Correo del usuario.
     * @bodyParam password string (opcional) Contraseña nueva.
     *
     * @response 200
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        try {
            DB::beginTransaction();

            $before = $user->only(['username', 'first_name', 'last_name', 'email', 'phone', 'status', 'position', 'department', 'address']);
            $oldEmail = $user->email;

            $data = $request->validated();
            $data['photo_profile'] = $request->file('photo_profile');
            $data['cv_file'] = $request->file('cv_file');

            $this->repository->update($user, $data);
            $user->refresh();
            $newEmail = $user->email;

            if ($oldEmail !== $newEmail) {
                $temporaryPassword = Str::random(12);
                $user->password = bcrypt($temporaryPassword);
                $user->must_change_password = true;
                $user->password_changed_at = null;
                $user->save();
                $user->tokens()->delete();

                $loginUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:4200')) . '/login';
                if (config('clicklab.email_via_api')) {
                    $html = view('emails.new-user-credentials', [
                        'user' => $user,
                        'temporaryPassword' => $temporaryPassword,
                        'loginUrl' => $loginUrl,
                    ])->render();
                    app(\App\Services\ClicklabClient::class)->sendEmail($user->email, 'Tus Credenciales de Acceso', $html);
                } else {
                    Mail::to($user->email)->send(new NewUserCredentialsMail($user, $temporaryPassword, $loginUrl));
                }
                if (config('clicklab.notify_on_user_update_email')) {
                    $channels = config('clicklab.channels', []);
                    $client = app(ClicklabClient::class);
                    $text = 'Actualización de acceso. Usuario: ' . $user->username . ' Clave temporal: ' . $temporaryPassword . ' Ingreso: ' . $loginUrl;
                    if (in_array('sms', $channels) && $user->phone) {
                        $client->sendSms($user->phone, $text);
                    }
                    if (in_array('whatsapp', $channels) && $user->phone) {
                        $tpl = config('clicklab.wa_template_name');
                        $ns  = config('clicklab.wa_template_namespace');
                        $lang= config('clicklab.wa_template_language');
                        if ($tpl) {
                            $client->sendWhatsappTemplate($user->phone, $tpl, [$user->first_name, $user->username, $temporaryPassword, $loginUrl], $lang, $ns);
                        } else {
                            $client->sendWhatsappText($user->phone, $text);
                        }
                    }
                }
            }
            
            if ($request->has('roles')) {
                $user->syncRoles($request->input('roles'));
                // Limpiar el caché de permisos después de actualizar roles
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
            }

            DB::commit();

            $after = $user->fresh()->only(['username', 'first_name', 'last_name', 'email', 'phone', 'status', 'position', 'department', 'address']);
            $changed = array_keys(array_diff_assoc($after, $before));
            UserActivityLog::log(
                $request->user()->user_id,
                UserActivityLog::ACTION_SECURITY_USER_UPDATED,
                'Usuario actualizado',
                [
                    'target_user_id' => $user->user_id,
                    'target_username' => $user->username,
                    'changed_fields' => $changed,
                    'roles' => $user->getRoleNames()->toArray(),
                ]
            );

            //PUSHER
            $pusher = $this->pusherInstance();
            $pusher->trigger('user-channel', 'updated', [
                'user' => (new UserResource($user->fresh('roles')))->toArray($request)
            ]);


            return new UserResource($user->fresh('roles'));



        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar usuario',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    /**
     * Remove the specified resource from storage.
     */
    /**
     * Eliminar usuario
     *
     * @urlParam id int required ID del usuario. Example: 2
     *
     * @response 200 {
     *  "message": "User deleted successfully"
     * }
     */
    public function destroy(User $user)
    {
        try {
            
            $userData = (new UserResource($user->fresh('roles')))->toArray(request());
            $actor = request()->user();
            
            $this->repository->delete($user);

            if ($actor) {
                UserActivityLog::log(
                    $actor->user_id,
                    UserActivityLog::ACTION_SECURITY_USER_DELETED,
                    'Usuario eliminado',
                    [
                        'target_user_id' => $userData['id'] ?? null,
                        'target_username' => $userData['username'] ?? null,
                        'target_email' => $userData['email'] ?? null,
                    ]
                );
            }

            $pusher = $this->pusherInstance();
            $pusher->trigger('user-channel', 'deleted', [
                'user' =>  $userData
            ]);

            return response()->json([
                'message' => 'Usuario eliminado correctamente',
            ], Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar usuario',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Cambiar contraseña del usuario
     *
     * @urlParam id int required ID del usuario. Example: 2
     * @bodyParam password string required Nueva contraseña. Example: NuevaClave123
     *
     * @response 200 {
     *  "message": "Contraseña actualizada correctamente"
     * }
     */
    public function changePassword(Request $request, User $user)
    {
        $request->validate([
            'password' => [
                'required',
                'string',
                'confirmed',
                \Illuminate\Validation\Rules\Password::min(8)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
            ],
        ], [
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.mixed_case' => 'La contraseña debe contener mayúsculas y minúsculas.',
            'password.numbers' => 'La contraseña debe contener al menos un número.',
            'password.symbols' => 'La contraseña debe contener al menos un símbolo especial.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user->update([
            'password_hash' => bcrypt($request->password),
            'must_change_password' => false, // Ya cambió la contraseña
            'password_changed_at' => now(),
        ]);
        $user->tokens()->delete();

        $actor = $request->user();
        if ($actor) {
            UserActivityLog::log(
                $actor->user_id,
                UserActivityLog::ACTION_SECURITY_USER_PASSWORD_RESET,
                'Contraseña restablecida',
                [
                    'target_user_id' => $user->user_id,
                    'target_username' => $user->username,
                ]
            );
        }

        return response()->json([
            'message' => 'Contraseña actualizada correctamente',
        ], Response::HTTP_OK);
    }


    /**
     * Activar o bloquear usuario
     *
     * @urlParam id int required ID del usuario. Example: 2
     *
     * @response 200 {
     *  "message": "Estado actualizado correctamente"
     * }
     */
    public function toggleStatus(User $user)
    {
        $user->status = $user->status === 'active' ? 'blocked' : 'active';
        $user->save();
        if ($user->status === 'blocked') {
            $user->tokens()->delete();
        }

        $actor = request()->user();
        if ($actor) {
            UserActivityLog::log(
                $actor->user_id,
                UserActivityLog::ACTION_SECURITY_USER_STATUS_UPDATED,
                'Estado de usuario actualizado',
                [
                    'target_user_id' => $user->user_id,
                    'target_username' => $user->username,
                    'status' => $user->status,
                ]
            );
        }

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'status' => $user->status
        ]);
    }

    public function syncRoles(Request $request, User $user)
    {
        $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $user->syncRoles($request->input('roles'));
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $actor = $request->user();
        if ($actor) {
            UserActivityLog::log(
                $actor->user_id,
                UserActivityLog::ACTION_SECURITY_USER_ROLES_UPDATED,
                'Roles de usuario actualizados',
                [
                    'target_user_id' => $user->user_id,
                    'target_username' => $user->username,
                    'roles' => $user->getRoleNames()->toArray(),
                ]
            );
        }

        return response()->json([
            'message' => 'Roles actualizados correctamente',
            'roles' => $user->getRoleNames(),
        ]);
    }

}
