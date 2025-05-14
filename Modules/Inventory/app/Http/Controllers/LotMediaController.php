<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Inventory\Models\LotMedia;

class LotMediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return LotMedia::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        //
        $data = $request->validate([
            'lot_id'      => 'required|exists:lots,lot_id',
            'url'         => 'required|url',
            'type'        => 'required|in:foto,plano,video,doc',
         ]);
        return LotMedia::create($data);
    }

    /**
     * Show the specified resource.
     */
    public function show(LotMedia $lotMedia)
    {
        //

        return $lotMedia;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        //

        return response()->json([]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LotMedia $lotMedia)
    {
        $lotMedia->delete();
        return response()->json([
            'message' => 'Media deleted successfully',
        ]);
    }
}
