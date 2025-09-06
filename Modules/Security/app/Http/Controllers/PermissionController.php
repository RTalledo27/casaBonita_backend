<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Security\Transformers\PermissionResource;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{


    //CONSTRUCTOR CON MIDDLEWARE
    public function __construct()
    {
        $this->middleware('can:security.permissions.view')->only(['index', 'show']);
        $this->middleware('can:security.permissions.store')->only(['store']);
        $this->middleware('can:security.permissions.update')->only(['update']);
        $this->middleware('can:security.permissions.destroy')->only(['destroy']);
    }
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        
        $query = Permission::query();
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        $permissions = $query->orderBy('name', 'asc')->paginate($perPage);
        
        return PermissionResource::collection($permissions);
    }

    public function show(Permission $permission)
    {
        return new PermissionResource($permission);
    }


    public function update(Request $request, Permission $permission)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $permission->id,
        ]);

        $permission->update([
            'name' => $request->name,
        ]);

        return new PermissionResource($permission);
    }
    public function destroy(Permission $permission)
    {
        $permission->delete();

        return response()->json([
            'message' => 'Permission deleted',
        ], Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
        ]);

        $permission = Permission::create([
            'name' => $request->name,
            'guard_name' => 'sanctum',
        ]);

        return (new PermissionResource($permission))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

}
