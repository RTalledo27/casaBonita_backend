<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Security\Models\User;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return User::with('roles')->paginate(15);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        $data = $request->validate([
            'username'      => 'required|string|unique:users,username',
            'password_hash' => 'required|string|min:60',
            'email'         => 'required|email|unique:users,email',
            'status'        => 'required|in:active,blocked',
            'photo_profile' => 'nullable|string'
        ]);
        return User::create($data);
    }

    /**
     * Show the specified resource.
     */
    public function show(User $user)
    {
        return $user->load('roles');
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
    public function update(Request $request, User $user) {
        $user->update($request->all());
        return $user;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user) {
        $user->delete();
        return response()->json([
            'message' => 'User deleted successfully'
        ])->status(200);
    }
}
