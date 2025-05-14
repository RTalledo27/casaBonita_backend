<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\Manzana;

class ManzanaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return Manzana::all();

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $data  = $request->validate([
            'name' => 'required|string|unique:manzana,name',
        ]);

    return Manzana::create($data);
    
}

    /**
     * Show the specified resource.
     */
    public function show(Manzana $manzana)
    {
        //

        return $manzana;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Manzana $manzana)
    {
        //

        $data = $request->validate([
            'name' => 'required|string|unique:manzana,name,' . $manzana->manzana_id . ',manzana_id',
        ]);

        $manzana->update($data);
        return $manzana;   
     }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Manzana $manzana)
    {
        //
        $manzana->delete();
        return response()->json([
            'message' => 'Manzana deleted successfully'
        ])->status(200);
    }
}
