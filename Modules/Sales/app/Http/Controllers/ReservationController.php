<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Models\Reservation;

class ReservationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return Reservation::with('lot', 'client', 'contract')->paginate(15);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'lot_id'           => 'required|exists:lots,lot_id',
            'client_id'        => 'required|exists:clients,client_id',
            'reservation_date' => 'required|date',
            'expiration_date'  => 'required|date|after_or_equal:reservation_date',
            'deposit_amount'   => 'nullable|numeric',
            'status'           => 'required|in:activa,expirada,cancelada,convertida',
        ]);

        return Reservation::create($data);
    }

    /**
     * Show the specified resource.
     */
    public function show(Reservation $reservation)
    {
        return $reservation->load('lot', 'client', 'contract');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'reservation_date' => 'sometimes|date',
            'expiration_date'  => 'sometimes|date|after_or_equal:reservation_date',
            'deposit_amount'   => 'nullable|numeric',
            'status'           => 'sometimes|in:activa,expirada,cancelada,convertida',
        ]);

        $reservation->update($data);
        return $reservation->load('lot', 'client', 'contract');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Reservation $reservation)
    {
        $reservation->delete();
        return response()->json([
            'message' => 'Reservation deleted successfully',
        ]);
    }
}
