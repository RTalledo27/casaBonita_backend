<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Security\Http\Requests\RoleRequest;
use Modules\Security\Models\Role;

class RoleController extends Controller
{

    public function __construct()
    {
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
        return Role::with('permissions')->get()->paginate(15);
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
        return response()->json(['message' => 'Rol creado correctamente', 'role' => $role], 201);
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
        return $role->load('permissions')->load('users');
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
        return response()->json(['message' => 'Rol actualizado correctamente', 'role' => $role]);
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
        $role->delete();
        return response()->json(['message' => 'Rol eliminado correctamente']);
    }
}
