<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Security\Http\Requests\StoreUserRequest;
use Modules\Security\Http\Requests\UpdateUserRequest;
use Modules\Security\Models\User;
use Modules\Security\Repositories\UserRepository;
use Modules\Security\Transformers\UserResource;
use Pusher\Pusher;
use Symfony\Component\HttpFoundation\Response;

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
    public function index()
    {
      

        $users = $this->repository->allWithRoles();
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


            $user = $this->repository->create($request->validated());

            if ($request->has('roles')) {
                $user->syncRoles($request->input('roles'));
            }

            $data['created_by'] = $request->user()->user_id;

            DB::commit();


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

            $this->repository->update($user, $request->validated());

            if ($request->has('roles')) {
                $user->syncRoles($request->input('roles'));
            }

            DB::commit();

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
            
            $this->repository->delete($user);




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
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user->update([
            'password' => bcrypt($request->password),
        ]);

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

        return response()->json([
            'message' => 'Estado actualizado correctamente',
            'status' => $user->status
        ]);
    }

}