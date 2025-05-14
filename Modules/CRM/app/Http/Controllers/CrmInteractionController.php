<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Models\CrmInteraction;

class CrmInteractionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return CrmInteraction::with('client', 'user')->paginate(15);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('crm::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,client_id',
            'user_id'   => 'required|exists:users,user_id',
            'date'      => 'required|date',
            'channel'   => 'required|in:call,email,whatsapp,visit,other',
            'notes'     => 'nullable|string',
        ]);

        return CrmInteraction::create($data);
    }

    /**
     * Show the specified resource.
     */
    public function show(CrmInteraction $crmInteraction)
    {
        return $crmInteraction->load('client', 'user');
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
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CrmInteraction $crm_interaction) {
        $crm_interaction->delete();
        return response()->json(['message' => 'Interaction deleted successfully.'], 200);
    }
}
