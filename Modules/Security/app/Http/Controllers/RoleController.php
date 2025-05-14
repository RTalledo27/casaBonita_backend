<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Security\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Role::paginate(15);
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
    public function store(Request $request) 
    {
        $data = $request->validate([
            'name'        => 'required|string|max:80',
            'description' => 'nullable|string|max:255',
        ]);

        return Role::create($data);

    }

    /**
     * Show the specified resource.
     */
    public function show(Role $role)
    {
        return $role->load('users');
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
    public function update(Request $request, Role $role) {
        $role->update($request->all());
        return $role;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role) {
        $role->delete();
        return response()->json(['message' => 'Role deleted successfully.'], 200);
    }
}
