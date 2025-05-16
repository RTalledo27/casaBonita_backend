<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SecurityController extends Controller
{

    /**
     * @group Seguridad - Autenticación
     *
     * Iniciar sesión del usuario en el sistema.
     *
     * Este endpoint permite autenticar un usuario y devolver un token de acceso.
     *
     * @bodyParam email string required El correo electrónico del usuario. Example: admin@erp.com
     * @bodyParam password string required La contraseña del usuario. Example: password123
     *
     * @response 200 {
     *  "token": "eyJ0eXAiOiJKV1QiLCJh...",
     *  "user": {
     *    "id": 1,
     *    "name": "Administrador",
     *    "email": "admin@erp.com",
     *    "role": "admin"
     *  }
     * }
     */
    
    /**
     * 
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('security::index');
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
