<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\ContractRequest;
use Modules\Sales\Http\Requests\ReservationRequest;
use Modules\Sales\Http\Requests\UpdateReservationRequest;
use Modules\Sales\Models\Reservation;
use Modules\Sales\Repositories\ContractRepository;
use Modules\Sales\Repositories\ReservationRepository;
use Modules\Sales\Transformers\ContractResource;
use Modules\Sales\Transformers\ReservationResource;
use Modules\services\PusherNotifier;

class ReservationController extends Controller
{

    public function __construct(
        private ReservationRepository $reservations,
        private ContractRepository $contracts,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.reservations.index')->only(['index', 'show']);
        $this->middleware('permission:sales.reservations.store')->only(['store']);
        $this->middleware('permission:sales.reservations.update')->only(['update', 'convert']);
        $this->middleware('permission:sales.reservations.destroy')->only(['destroy']);

        $this->authorizeResource(Reservation::class, 'reservation');
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //

        return ReservationResource::collection($this->reservations->paginate());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ReservationRequest $request)
    {
        $reservation = $this->reservations->create($request->validated());
        $this->pusher->notify('reservation', 'created', ['reservation' => new ReservationResource($reservation)]);
        return new ReservationResource($reservation);
    }

    /**
     * Show the specified resource.
     */
    public function show(Reservation $reservation)
    {
        return new ReservationResource($reservation->load('lot', 'client', 'contract'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        $updated = $this->reservations->update($reservation, $request->validated());
        $this->pusher->notify('reservation', 'updated', ['reservation' => new ReservationResource($updated)]);
        return new ReservationResource($updated);
    }


    /**
     * Convert reservation to contract.
     */
    public function convert(ContractRequest $request, Reservation $reservation)
    {
        if ($reservation->contract) {
            return response()->json(['message' => 'Reservation already converted'], 409);
        }
        $reservation->update(['status' => 'convertida']);
        $contract = $this->contracts->create(array_merge($request->validated(), [
            'reservation_id' => $reservation->reservation_id,
            'status'         => 'vigente',
        ]));
        $this->pusher->notify('reservation-channel', 'converted', ['reservation' => new ReservationResource($reservation)]);
        return new ContractResource($contract);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Reservation $reservation)
    {
       
        $id = $reservation->reservation_id;
        $this->reservations->delete($reservation);
        $this->pusher->notify('reservation', 'deleted', ['reservation' => ['reservation_id' => $id]]);
        return response()->json(['message' => 'Reservation deleted successfully']);
    }
}
