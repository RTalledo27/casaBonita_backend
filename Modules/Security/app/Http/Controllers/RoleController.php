<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Modules\Security\Http\Requests\RoleRequest;
use Modules\Security\Models\Role;
use Modules\Security\Transformers\RoleResource;
use Pusher\Pusher;

class RoleController extends Controller
{


    private function pusherInstance()
    {
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


    public function __construct()
    {
        $this->middleware('can:security.roles.view')->only(['index', 'show']);
        $this->middleware('can:security.roles.store')->only(['store']);
        $this->middleware('can:security.roles.update')->only(['update']);
        $this->middleware('can:security.roles.destroy')->only(['destroy']);
        $this->middleware('auth:sanctum');
    }


    /**
     * Listar roles
     *
     * Devuelve todos los roles con sus permisos.
     *
     * @response 200
     */
    public function index()
    {
        return RoleResource::collection(Role::with('permissions')->get());
    }

    

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
    /**
     * Crear nuevo rol
     *
     * @bodyParam name string required Nombre único del rol. Example: editor
     * @bodyParam permissions array Lista de permisos a asignar. Example: ["view_users", "edit_users"]
     *
     * @response 201
     */
    public function store(RoleRequest $request)
    {
        $role = Role::create(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        //pusher:
        $pusher = $this->pusherInstance();
        $pusher->trigger('role-channel', 'created', [
            'role' => (new RoleResource($role->load('permissions')))->toArray($request)
        ]);

        return response()->json([
            'message' => 'Rol creado correctamente',
            'data' => new RoleResource($role->load('permissions'))
        ], Response::HTTP_CREATED);
    }


    /**
     * Mostrar rol específico
     *
     * @urlParam id integer ID del rol. Example: 1
     *
     * @response 200
     */
    public function show(Role $role)
    {
        return new RoleResource($role->load(['permissions', 'users']));
    }



  

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('security::edit');
    }


    /**
     * Actualizar rol
     *
     * @urlParam id integer ID del rol. Example: 1
     * @bodyParam name string Nuevo nombre del rol.
     * @bodyParam permissions array Lista de permisos a asignar.
     *
     * @response 200
     */
    public function update(RoleRequest $request, Role $role)
    {
        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        $pusher = $this->pusherInstance();
        $pusher->trigger('role-channel', 'updated', [
            'role' => (new RoleResource($role->load('permissions')))->toArray($request)
        ]);
        


        return response()->json([
            'message' => 'Rol actualizado correctamente',
            'data' => new RoleResource($role->load('permissions'))
        ], Response::HTTP_OK);
    
    }


    /**
     * Asignar permisos a un rol
     *
     * @urlParam role string required Nombre del rol. Example: admin
     * @bodyParam permissions array required Lista de permisos. Example: ["security.users.index", "security.users.store"]
     *
     * @response 200 {
     *  "message": "Permisos asignados correctamente",
     *  "permissions": ["security.users.index", "security.users.store"]
     * }
     */

    public function syncPermissions(Request $request, Role $role)
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        $role->syncPermissions($request->permissions);

        return response()->json([
            'message' => 'Permisos asignados correctamente',
            'permissions' => $role->permissions->pluck('name')
        ]);
    }



    /**
     * Eliminar rol
     *
     * @urlParam id integer ID del rol. Example: 1
     *
     * @response 200
     */
    public function destroy(Role $role)
    {
        $roleData = (new RoleResource($role->load('permissions')))->toArray(request());

        $role->delete();

        $pusher = $this->pusherInstance();
        $pusher->trigger('role-channel', 'deleted', [
            'role' => $roleData
        ]);
        return response()->json(['message' => 'Rol eliminado correctamente']);
    }
}
