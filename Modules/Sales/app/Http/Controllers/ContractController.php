<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Contract;

class ContractController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return Contract::with('reservation', 'schedules', 'invoices')->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'reservation_id'  => 'required|exists:reservations,reservation_id',
            'contract_number' => 'required|string|unique:contracts,contract_number',
            'sign_date'       => 'required|date',
            'total_price'     => 'required|numeric',
            'currency'        => 'required|string|size:3',
            'status'          => 'required|in:vigente,resuelto,cancelado',
        ]);

        return Contract::create($data);
    }
    /**
     * Show the specified resource.
     */
    public function show(Contract $contract)
    {
        return $contract->load('reservation', 'schedules', 'invoices');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contract $contract)
    {
        $data = $request->validate([
            'reservation_id'  => 'sometimes|exists:reservations,reservation_id',
            'contract_number' => 'sometimes|string|unique:contracts,contract_number,' . $contract->contract_id . ',contract_id',
            'sign_date'       => 'sometimes|date',
            'total_price'     => 'sometimes|numeric',
            'currency'        => 'sometimes|string|size:3',
            'status'          => 'sometimes|in:vigente,resuelto,cancelado',
        ]);

        $contract->update($data);
        return $contract->load('reservation', 'schedules', 'invoices');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Contract $contract)
    {
        //
        $contract->delete();
        return response()->json([
            'message' => 'Contract deleted successfully'
        ])->status(200);    
    }
}
