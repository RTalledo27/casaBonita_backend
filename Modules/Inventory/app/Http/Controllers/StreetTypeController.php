<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\StreetType;

class StreetTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return StreetType::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $data  = $request->validate([
            'name' => 'required|string|unique:street_type,name',
        ]);
        return StreetType::create($data);
    }

    /**
     * Show the specified resource.
     */
    public function show(StreetType $streetType)
    {
        //

        return $streetType;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StreetType $streetType)
    {
        //

        $data = $request->validate([
            'name' => 'required|string|unique:street_type,name,' . $streetType->street_type_id . ',street_type_id',
        ]);

        $streetType->update($data);
        return $streetType;   
     }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StreetType $streetType)
    {
        //

        $streetType->delete();
        return response()->json([
            'message' => 'Street type deleted successfully'
        ]);
    }
}
