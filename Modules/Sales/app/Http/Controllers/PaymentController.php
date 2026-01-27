<?php

namespace Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Sales\Http\Requests\PaymentRequest;
use Modules\Sales\Http\Requests\UpdatePaymentRequest;
use Modules\Sales\Models\Payment;
use Modules\Sales\Repositories\PaymentRepository;
use Modules\Sales\Transformers\PaymentResource;
use Modules\Services\PusherNotifier;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentRepository $payments,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.payments.view')->only(['index', 'show', 'ledger', 'summary', 'downloadVoucher']);
        $this->middleware('permission:sales.payments.store')->only('store');
        $this->middleware('permission:sales.payments.update')->only(['update', 'uploadVoucher']);
        $this->middleware('permission:sales.payments.destroy')->only('destroy');
    }

    protected function buildLedgerQuery(Carbon $start, Carbon $end)
    {
        $logicwarePaymentAgg = DB::table('logicware_payments')
            ->select(
                'schedule_id',
                DB::raw('MAX(method) as method'),
                DB::raw('MAX(bank_name) as bank_name'),
                DB::raw('MAX(reference_number) as reference_number')
            )
            ->whereNotNull('schedule_id')
            ->groupBy('schedule_id');

        $paymentAgg = DB::table('payments')
            ->select(
                'schedule_id',
                DB::raw('MAX(payment_id) as payment_id'),
                DB::raw('MAX(payment_date) as payment_date'),
                DB::raw('SUM(amount) as amount'),
                DB::raw('MAX(method) as method'),
                DB::raw('MAX(reference) as reference')
            )
            ->groupBy('schedule_id');

        $payments = DB::table('payments as p')
            ->leftJoin('payment_schedules as ps', 'p.schedule_id', '=', 'ps.schedule_id')
            ->leftJoin('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('clients as cl', function ($join) {
                $join->on('cl.client_id', '=', DB::raw('COALESCE(c.client_id, r.client_id)'));
            })
            ->leftJoin('lots as l', function ($join) {
                $join->on('l.lot_id', '=', DB::raw('COALESCE(c.lot_id, r.lot_id)'));
            })
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->whereBetween('p.payment_date', [$start->toDateString(), $end->toDateString()])
            ->select(
                DB::raw('p.payment_id as row_key'),
                DB::raw("CONCAT('payment-', p.payment_id) as id"),
                DB::raw("'payment' as source"),
                DB::raw("'installment' as movement_type"),
                'p.payment_id',
                DB::raw('NULL as reservation_id'),
                'p.schedule_id',
                'c.contract_id',
                'c.contract_number',
                DB::raw('COALESCE(c.client_id, r.client_id) as client_id'),
                DB::raw('CONCAT(COALESCE(cl.first_name, ""), " ", COALESCE(cl.last_name, "")) as client_name'),
                DB::raw('COALESCE(c.lot_id, r.lot_id) as lot_id'),
                DB::raw('CONCAT(COALESCE(m.name, ""), " - Lote ", COALESCE(l.num_lot, "")) as lot_name'),
                'p.amount',
                'p.method',
                DB::raw('NULL as bank_name'),
                'p.reference',
                DB::raw('CASE WHEN p.voucher_path IS NULL OR p.voucher_path = "" THEN 0 ELSE 1 END as has_voucher'),
                DB::raw('p.payment_date as date'),
                'ps.installment_number',
                'ps.type as installment_type',
                'ps.due_date'
            );

        $paidSchedulesWithoutPayment = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->leftJoinSub($paymentAgg, 'pa', function ($join) {
                $join->on('pa.schedule_id', '=', 'ps.schedule_id');
            })
            ->leftJoinSub($logicwarePaymentAgg, 'lpa', function ($join) {
                $join->on('lpa.schedule_id', '=', 'ps.schedule_id');
            })
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('clients as cl', function ($join) {
                $join->on('cl.client_id', '=', DB::raw('COALESCE(c.client_id, r.client_id)'));
            })
            ->leftJoin('lots as l', function ($join) {
                $join->on('l.lot_id', '=', DB::raw('COALESCE(c.lot_id, r.lot_id)'));
            })
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->where('ps.status', 'pagado')
            ->whereNotNull('ps.paid_date')
            ->whereBetween(DB::raw('DATE(ps.paid_date)'), [$start->toDateString(), $end->toDateString()])
            ->whereNull('pa.payment_id')
            ->select(
                DB::raw('(1000000000 + ps.schedule_id) as row_key'),
                DB::raw("CONCAT('schedule-', ps.schedule_id) as id"),
                DB::raw("'schedule' as source"),
                DB::raw("'installment' as movement_type"),
                DB::raw('NULL as payment_id'),
                DB::raw('NULL as reservation_id'),
                'ps.schedule_id',
                'c.contract_id',
                'c.contract_number',
                DB::raw('COALESCE(c.client_id, r.client_id) as client_id'),
                DB::raw('CONCAT(COALESCE(cl.first_name, ""), " ", COALESCE(cl.last_name, "")) as client_name'),
                DB::raw('COALESCE(c.lot_id, r.lot_id) as lot_id'),
                DB::raw('CONCAT(COALESCE(m.name, ""), " - Lote ", COALESCE(l.num_lot, "")) as lot_name'),
                DB::raw('COALESCE(ps.logicware_paid_amount, ps.amount) as amount'),
                DB::raw("COALESCE(NULLIF(lpa.method, ''), NULL) as method"),
                DB::raw("COALESCE(NULLIF(lpa.bank_name, ''), NULL) as bank_name"),
                DB::raw("COALESCE(NULLIF(lpa.reference_number, ''), NULL) as reference"),
                DB::raw('0 as has_voucher'),
                DB::raw('ps.paid_date as date'),
                'ps.installment_number',
                'ps.type as installment_type',
                'ps.due_date'
            );

        $reservationDeposits = DB::table('reservations as r')
            ->leftJoin('contracts as c', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('clients as cl', 'cl.client_id', '=', 'r.client_id')
            ->leftJoin('lots as l', 'l.lot_id', '=', 'r.lot_id')
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->whereNotNull('r.deposit_paid_at')
            ->whereBetween(DB::raw('DATE(r.deposit_paid_at)'), [$start->toDateString(), $end->toDateString()])
            ->select(
                DB::raw('(2000000000 + r.reservation_id) as row_key'),
                DB::raw("CONCAT('reservation-', r.reservation_id) as id"),
                DB::raw("'reservation' as source"),
                DB::raw("'reservation_deposit' as movement_type"),
                DB::raw('NULL as payment_id'),
                'r.reservation_id',
                DB::raw('NULL as schedule_id'),
                'c.contract_id',
                'c.contract_number',
                'r.client_id',
                DB::raw('CONCAT(COALESCE(cl.first_name, ""), " ", COALESCE(cl.last_name, "")) as client_name'),
                'r.lot_id',
                DB::raw('CONCAT(COALESCE(m.name, ""), " - Lote ", COALESCE(l.num_lot, "")) as lot_name'),
                DB::raw('COALESCE(r.deposit_amount, 0) as amount'),
                DB::raw("COALESCE(NULLIF(r.deposit_method, ''), NULL) as method"),
                DB::raw('NULL as bank_name'),
                DB::raw("COALESCE(NULLIF(r.deposit_reference, ''), NULL) as reference"),
                DB::raw('0 as has_voucher'),
                DB::raw('r.deposit_paid_at as date'),
                DB::raw('NULL as installment_number'),
                DB::raw('NULL as installment_type'),
                DB::raw('NULL as due_date')
            );

        $union = $payments
            ->unionAll($paidSchedulesWithoutPayment)
            ->unionAll($reservationDeposits);

        return DB::query()
            ->fromSub($union, 'ledger')
            ->orderByDesc('date');
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

    public function ledger(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $perPage = (int) ($request->query('per_page', 50));
        $perPage = max(1, min(200, $perPage));

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        $paginated = $this->buildLedgerQuery($start, $end)->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated,
            'meta' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ],
        ]);
    }

    public function summary(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        $items = $this->buildLedgerQuery($start, $end)->limit(5000)->get()->map(fn ($r) => (array) $r)->all();

        $totalAmount = 0.0;
        foreach ($items as $it) {
            $totalAmount += (float) ($it['amount'] ?? 0);
        }

        $byMethod = [];
        $byType = [];
        foreach ($items as $it) {
            $m = $it['method'] ?? 'no_especificado';
            if (!isset($byMethod[$m])) {
                $byMethod[$m] = ['method' => $m, 'count' => 0, 'amount' => 0.0];
            }
            $byMethod[$m]['count'] += 1;
            $byMethod[$m]['amount'] += (float) ($it['amount'] ?? 0);

            $t = $it['movement_type'] ?? 'unknown';
            if (!isset($byType[$t])) {
                $byType[$t] = ['type' => $t, 'count' => 0, 'amount' => 0.0];
            }
            $byType[$t]['count'] += 1;
            $byType[$t]['amount'] += (float) ($it['amount'] ?? 0);
        }

        usort($items, fn ($a, $b) => ((float)($b['amount'] ?? 0)) <=> ((float)($a['amount'] ?? 0)));
        $top = array_slice($items, 0, 10);

        $overdueBase = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->where('c.status', 'vigente')
            ->where('ps.status', '!=', 'pagado')
            ->whereNotNull('ps.due_date')
            ->whereDate('ps.due_date', '<=', $end->toDateString())
            ->where(function ($q) {
                $q->whereNull('ps.type')->orWhere('ps.type', '!=', 'bono_bpp');
            });

        $dueSoonBase = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->where('c.status', 'vigente')
            ->where('ps.status', '!=', 'pagado')
            ->whereNotNull('ps.due_date')
            ->whereDate('ps.due_date', '>', $end->toDateString())
            ->whereDate('ps.due_date', '<=', $end->copy()->addDays(7)->toDateString())
            ->where(function ($q) {
                $q->whereNull('ps.type')->orWhere('ps.type', '!=', 'bono_bpp');
            });

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
                'total_payments_count' => count($items),
                'total_payments_received' => round($totalAmount, 2),
                'payments_by_method' => array_values($byMethod),
                'payments_by_type' => array_values($byType),
                'top_payments' => $top,
                'collections_alerts' => [
                    'overdue' => [
                        'count' => (int) $overdueBase->count(),
                        'amount' => (float) ($overdueBase->sum('ps.amount') ?? 0),
                    ],
                    'due_soon' => [
                        'days' => 7,
                        'count' => (int) $dueSoonBase->count(),
                        'amount' => (float) ($dueSoonBase->sum('ps.amount') ?? 0),
                    ],
                ],
            ],
        ]);
    }

    public function uploadVoucher(Request $request, Payment $payment)
    {
        $request->validate([
            'voucher' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
        ]);

        $file = $request->file('voucher');
        if (!$file) {
            return response()->json(['message' => 'Archivo voucher no encontrado'], 422);
        }

        DB::beginTransaction();
        try {
            if (!empty($payment->voucher_path)) {
                Storage::disk('public')->delete($payment->voucher_path);
            }

            $path = Storage::disk('public')->putFile('payments/vouchers', $file);
            $payment->voucher_path = $path;
            $payment->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Voucher subido correctamente',
                'data' => [
                    'payment_id' => $payment->payment_id,
                    'has_voucher' => true,
                    'voucher_url' => url("/api/v1/sales/payments/{$payment->payment_id}/voucher"),
                ],
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al subir voucher',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadVoucher(Request $request, Payment $payment)
    {
        if (empty($payment->voucher_path)) {
            return response()->json(['message' => 'Este pago no tiene voucher'], 404);
        }

        if (!Storage::disk('public')->exists($payment->voucher_path)) {
            return response()->json(['message' => 'Voucher no encontrado en almacenamiento'], 404);
        }

        return Storage::disk('public')->download($payment->voucher_path);
    }
}
