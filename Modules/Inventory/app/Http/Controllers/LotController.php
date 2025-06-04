<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\Lot;

class LotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return Lot::with('manzana', 'street_type','media')->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $data = $request->validate([
            'manzana_id'             => 'required|exists:manzana,manzana_id',
            'street_type_id'         => 'required|exists:street_type,street_type_id',
            'num_lot'                => 'required|integer',
            'area_m2'                => 'required|numeric',
            'area_construction_m2'   => 'nullable|numeric',
            'total_price'            => 'required|numeric',
            'funding'                => 'nullable|numeric',
            'BPP'                    => 'nullable|numeric',
            'BFH'                    => 'nullable|numeric',
            'initial_quota'          => 'nullable|numeric',
            'currency'               => 'required|string|size:3',
            'status'                 => 'required|in:disponible,reservado,vendido',
        ]);

        return Lot::create($data);
        
    }

    /**
     * Show the specified resource.
     */
    public function show(Lot $lot)
    {
        //

        return $lot->load('manzana', 'streetType', 'media');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Lot $lot)
    {
        //

        $data = $request->validate([
            'manzana_id'             => 'sometimes|exists:manzana,manzana_id',
            'street_type_id'         => 'sometimes|exists:street_type,street_type_id',
            'num_lot'                => 'sometimes|integer',
            'area_m2'                => 'sometimes|numeric',
            'area_construction_m2'   => 'nullable|numeric',
            'total_price'            => 'sometimes|numeric',
            'funding'                => 'nullable|numeric',
            'BPP'                    => 'nullable|numeric',
            'BFH'                    => 'nullable|numeric',
            'initial_quota'          => 'nullable|numeric',
            'currency'               => 'sometimes|string|size:3',
            'status'                 => 'sometimes|in:disponible,reservado,vendido',
        ]);

        $lot->update($data);
        return $lot->load('block', 'streetType', 'media');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        //

        return response()->json([]);
    }
}
