<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Sales\Http\Requests\ConfirmReservationPaymentRequest;
use Modules\Sales\Http\Requests\ContractRequest;
use Modules\Sales\Http\Requests\ConvertReservationRequest;
use Modules\Sales\Http\Requests\ReservationRequest;
use Modules\Sales\Http\Requests\UpdateReservationRequest;
use Modules\Sales\Models\Reservation;
use Modules\Sales\Repositories\ContractRepository;
use Modules\Sales\Repositories\ReservationRepository;
use Modules\Sales\Transformers\ContractResource;
use Modules\Sales\Transformers\ReservationResource;
use Modules\Services\PusherNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{

    public function __construct(
        private ReservationRepository $reservations,
        private ContractRepository $contracts,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.reservations.view')->only(['index', 'show']);
        $this->middleware('permission:sales.reservations.store')->only(['store']);
        $this->middleware('permission:sales.reservations.update')->only(['update', 'convert', 'confirmPayment']);
        $this->middleware('permission:sales.reservations.destroy')->only(['destroy']);

        $this->authorizeResource(Reservation::class, 'reservation');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {

        $perPage = (int) $request->query('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $filters = $request->only([
            'search',
            'status',
            'lot_id',
            'client_id',
            'advisor_id',
            'reservation_date_from',
            'reservation_date_to',
        ]);

        return ReservationResource::collection($this->reservations->paginate($perPage, $filters));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ReservationRequest $request)
    {
        $reservation = $this->reservations->create($request->validated()); // Persist reservation
        // Broadcast that a reservation was created
        $this->pusher->notify('reservation-channel', 'created', ['reservation' => new ReservationResource($reservation)]);
        return new ReservationResource($reservation);
    }

    /**
     * Confirm reservation payment.
     */
    public function confirmPayment(ConfirmReservationPaymentRequest $request, Reservation $reservation)
    {
        $updated = $this->reservations->confirmPayment($reservation, $request->validated());
        $this->pusher->notify('reservation-channel', 'payment_confirmed', [
            'reservation' => new ReservationResource($updated)
        ]);
        return new ReservationResource($updated);
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
    public function convert(ConvertReservationRequest $request, Reservation $reservation)
    {
        if ($reservation->contract) {
            return response()->json(['message' => 'Esta reserva ya fue convertida a contrato'], 409);
        }

        // Validar que el cliente no tenga ya un contrato vigente
        $existingClientContract = \Modules\Sales\Models\Contract::where('status', 'vigente')
            ->where(function ($q) use ($reservation) {
                // Contratos directos (client_id en contracts)
                $q->where('client_id', $reservation->client_id)
                  // Contratos desde reserva (client_id en reservations)
                  ->orWhereHas('reservation', function ($rq) use ($reservation) {
                      $rq->where('client_id', $reservation->client_id);
                  });
            })
            ->first();

        if ($existingClientContract) {
            return response()->json([
                'message' => 'Este cliente ya tiene un contrato vigente (Nº ' . $existingClientContract->contract_number . '). No se puede crear otro.',
            ], 422);
        }

        // Validar que el lote no esté ya vendido en otro contrato
        $existingLotContract = \Modules\Sales\Models\Contract::where('status', 'vigente')
            ->where(function ($q) use ($reservation) {
                $q->where('lot_id', $reservation->lot_id)
                  ->orWhereHas('reservation', function ($rq) use ($reservation) {
                      $rq->where('lot_id', $reservation->lot_id);
                  });
            })
            ->first();

        if ($existingLotContract) {
            return response()->json([
                'message' => 'Este lote ya tiene un contrato vigente (Nº ' . $existingLotContract->contract_number . '). No se puede vender dos veces.',
            ], 422);
        }

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Separar campos de cronograma de los datos del contrato
            $scheduleStartDate = $validated['schedule_start_date'] ?? null;
            $scheduleFrequency = $validated['schedule_frequency'] ?? null;
            unset($validated['schedule_start_date'], $validated['schedule_frequency']);

            $reservation->update(['status' => 'convertida']);

            // chk_contract_source: cuando hay reservation_id, client_id y lot_id deben ser NULL
            // (el cliente y lote se obtienen a través de la relación reservation)
            $contract = $this->contracts->create(array_merge($validated, [
                'reservation_id' => $reservation->reservation_id,
                'client_id'      => null,
                'lot_id'         => null,
                'advisor_id'     => $reservation->advisor_id,
                'status'         => 'vigente',
            ]));

            // Actualizar lote a 'vendido'
            if ($lot = $reservation->lot) {
                $lot->update(['status' => 'vendido']);
            }

            // Generar cronograma automáticamente si se proporcionan los datos
            $schedulesGenerated = 0;
            if ($scheduleStartDate && $scheduleFrequency) {
                $startDate = new \DateTime($scheduleStartDate);
                $financingAmount = $contract->financing_amount;
                $interestRate = $contract->interest_rate;
                $termMonths = $contract->term_months;

                // Calcular cuota mensual
                if ($interestRate == 0) {
                    $monthlyPayment = $financingAmount / $termMonths;
                } else {
                    $monthlyRate = $interestRate / 100 / 12;
                    $monthlyPayment = $financingAmount * ($monthlyRate * pow(1 + $monthlyRate, $termMonths)) / (pow(1 + $monthlyRate, $termMonths) - 1);
                }

                // Ajustar según frecuencia
                $paymentAmount = $monthlyPayment;
                $intervalDays = 30;
                $totalPayments = $termMonths;

                switch ($scheduleFrequency) {
                    case 'biweekly':
                        $paymentAmount = $monthlyPayment / 2;
                        $intervalDays = 14;
                        $totalPayments = $termMonths * 2;
                        break;
                    case 'weekly':
                        $paymentAmount = $monthlyPayment / 4;
                        $intervalDays = 7;
                        $totalPayments = $termMonths * 4;
                        break;
                }

                $schedules = [];
                $currentDate = clone $startDate;
                $installmentNumber = 1;

                // Cuota inicial (enganche)
                $initialQuota = $contract->down_payment ?? 0;
                if ($initialQuota > 0) {
                    $schedules[] = [
                        'contract_id' => $contract->contract_id,
                        'installment_number' => $installmentNumber,
                        'due_date' => $currentDate->format('Y-m-d'),
                        'amount' => round($initialQuota, 2),
                        'status' => 'pendiente',
                        'notes' => 'Cuota inicial',
                        'type' => 'inicial',
                    ];
                    $installmentNumber++;
                    $currentDate->add(new \DateInterval('P' . $intervalDays . 'D'));
                }

                // Cuotas regulares
                for ($i = $installmentNumber; $i <= $totalPayments + ($initialQuota > 0 ? 1 : 0); $i++) {
                    $schedules[] = [
                        'contract_id' => $contract->contract_id,
                        'installment_number' => $i,
                        'due_date' => $currentDate->format('Y-m-d'),
                        'amount' => round($paymentAmount, 2),
                        'status' => 'pendiente',
                        'type' => 'financiamiento',
                    ];
                    $currentDate->add(new \DateInterval('P' . $intervalDays . 'D'));
                }

                // Cuota balón si existe
                $balloonPayment = $contract->balloon_payment ?? 0;
                if ($balloonPayment > 0) {
                    $schedules[] = [
                        'contract_id' => $contract->contract_id,
                        'installment_number' => count($schedules) + 1,
                        'due_date' => $currentDate->format('Y-m-d'),
                        'amount' => round($balloonPayment, 2),
                        'status' => 'pendiente',
                        'notes' => 'Cuota balón',
                        'type' => 'balon',
                    ];
                }

                if (!empty($schedules)) {
                    \Modules\Collections\Models\PaymentSchedule::insert($schedules);
                    $schedulesGenerated = count($schedules);
                }

                Log::info('[Reservation→Contract] Cronograma generado', [
                    'contract_id' => $contract->contract_id,
                    'schedules' => $schedulesGenerated,
                    'frequency' => $scheduleFrequency,
                ]);
            }

            DB::commit();

            // Recargar contrato con relaciones
            $contract->load(['reservation.client', 'reservation.lot', 'client', 'lot', 'advisor', 'schedules']);

            $this->pusher->notify('reservation-channel', 'converted', [
                'reservation' => new ReservationResource($reservation),
                'schedules_generated' => $schedulesGenerated,
            ]);

            return new ContractResource($contract);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Reservation→Contract] Error en conversión', [
                'reservation_id' => $reservation->reservation_id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Error al convertir reserva',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Reservation $reservation)
    {

        $id = $reservation->reservation_id; // Preserve ID for notification
        $this->reservations->delete($reservation); // Remove reservation
        // Notify about the deletion
        $this->pusher->notify('reservation', 'deleted', ['reservation' => ['reservation_id' => $id]]);
        return response()->json(['message' => 'Reservation deleted successfully']);
    }
}
