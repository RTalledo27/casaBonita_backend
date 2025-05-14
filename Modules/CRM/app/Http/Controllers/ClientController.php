<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Models\Client;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Client::with('addresses', 'interactions', 'spouses')
            ->paginate(15);
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
            'first_name'  => 'required|string|max:80',
            'last_name'   => 'required|string|max:80',
            'doc_type'    => 'required|in:DNI,CE,RUC,PAS',
            'doc_number'  => 'required|string|max:20|unique:clients',
            'email'       => 'nullable|email|max:120',
            'primary_phone' => 'nullable|integer',
            'secondary_phone' => 'nullable|integer',
            'marital_status' => 'required|in:soltero,casado,divorciado,viudo',
            'type'        => 'required|in:lead,client,provider',
            'occupation'  => 'nullable|string|max:80',
            'salary'      => 'nullable|numeric',
            'date'        => 'nullable|date',
        ]);


        return Client::create($data);
    }

    /**
     * Show the specified resource.
     */
    public function show($request, Client $client)
    {
        return $client->load('addresses', 'interactions', 'spouses');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('crm::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Client $client)  {
        $client->update($request->all());
        return $client;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Client $client)
    {
        $client->delete();
        return response()->json(['message' => 'Client deleted successfully.'], 200);
        
    }
}
