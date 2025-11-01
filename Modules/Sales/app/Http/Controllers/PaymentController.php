<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Sales\Http\Requests\PaymentRequest;
use Modules\Sales\Http\Requests\UpdatePaymentRequest;
use Modules\Sales\Models\Payment;
use Modules\Sales\Repositories\PaymentRepository;
use Modules\Sales\Transformers\PaymentResource;
use Modules\services\PusherNotifier;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentRepository $payments,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.payments.view')->only(['index', 'show']);
        $this->middleware('permission:sales.payments.store')->only('store');
        $this->middleware('permission:sales.payments.update')->only('update');
        $this->middleware('permission:sales.payments.destroy')->only('destroy');
    }

    public function index()
    {
        return PaymentResource::collection(
            $this->payments->paginate()
        );
    }

    public function store(PaymentRequest $request)
    {
        DB::beginTransaction();
        try {
            $payment = $this->payments->create($request->validated());
            
            // Registrar actividad
            UserActivityLog::log(
                $request->user()->user_id,
                UserActivityLog::ACTION_PAYMENT_REGISTERED,
                "Pago registrado por $" . number_format($payment->amount_paid, 2),
                [
                    'payment_id' => $payment->payment_id,
                    'amount' => $payment->amount_paid,
                    'schedule_id' => $payment->payment_schedule_id,
                ]
            );
            
            DB::commit();

            $this->pusher->notify('payment-channel', 'created', [
                'payment' => (new PaymentResource($payment))->toArray($request),
            ]);

            return (new PaymentResource($payment))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al registrar pago',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show(Payment $payment)
    {
        return new PaymentResource($payment->load(['schedule', 'journalEntry']));
    }

    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        DB::beginTransaction();
        try {
            $updated = $this->payments->update($payment, $request->validated());
            DB::commit();

            $this->pusher->notify('payment-channel', 'updated', [
                'payment' => (new PaymentResource($updated))->toArray($request),
            ]);

            return new PaymentResource($updated);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar pago',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(Payment $payment)
    {
        try {
            DB::beginTransaction();
            $resource = new PaymentResource($payment->load(['schedule', 'journalEntry']));
            $this->payments->delete($payment);
            DB::commit();

            $this->pusher->notify('payment-channel', 'deleted', [
                'payment' => $resource->toArray(request()),
            ]);

            return response()->json(['message' => 'Pago eliminado correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al eliminar pago',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
