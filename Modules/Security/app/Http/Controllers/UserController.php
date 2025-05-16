<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Security\Http\Requests\StoreUserRequest;
use Modules\Security\Http\Requests\UpdateUserRequest;
use Modules\Security\Models\User;
use Modules\Security\Repositories\UserRepository;
use Modules\Security\Transformers\UserResource;

class UserController extends Controller
{
    /**
     * @group Seguridad - Gestión de Usuarios
     *
     * Endpoints para administrar usuarios del sistema.
     */


    //CONSTRUCTOR
    public function __construct(private UserRepository $users)
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(User::class, 'user');
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
        $filters = request()->only(['search', 'sort_by', 'sort_dir', 'per_page']);
        $paginated = $this->users->paginate($filters);
        return UserResource::collection($paginated);
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
        $user = $this->users->create($request->validated());
        $user->assignRole($request->role);
        return new UserResource($user);
    }

    /**
     * Ver usuario específico
     *
     * @urlParam id int required ID del usuario. Example: 2
     * @response 200
     */
    public function show(User $user)
    {
        return new UserResource($user->load('roles'));
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
        $user = $this->users->update($user, $request->validated());
        return new UserResource($user);
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
        $this->users->delete($user);
        return response()->json([
            'message' => 'User deleted successfully'
        ], 200);
    }
}
