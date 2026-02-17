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
use Modules\Sales\Models\PaymentSchedule;
use Modules\Sales\Repositories\PaymentRepository;
use Modules\Sales\Transformers\PaymentResource;
use Modules\Services\PusherNotifier;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentRepository $payments,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:sales.payments.view')->only(['index', 'show', 'ledger', 'summary', 'fivePercentReport', 'exportFivePercentReport', 'downloadVoucher']);
        $this->middleware('permission:sales.payments.store')->only('store');
        $this->middleware('permission:sales.payments.update')->only(['update', 'uploadVoucher', 'updateSchedulePayment']);
        $this->middleware('permission:sales.payments.destroy')->only(['destroy', 'revertSchedule']);
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
            ->leftJoin('payment_transactions as pt', 'p.transaction_id', '=', 'pt.transaction_id')
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
                'p.transaction_id',
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
                DB::raw('COALESCE(pt.reference, p.reference) as reference'),
                DB::raw('CASE WHEN COALESCE(pt.voucher_path, p.voucher_path) IS NULL OR COALESCE(pt.voucher_path, p.voucher_path) = "" THEN 0 ELSE 1 END as has_voucher'),
                DB::raw('pt.voucher_path as transaction_voucher_path'),
                DB::raw('p.voucher_path as payment_voucher_path'),
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
                DB::raw('NULL as transaction_id'),
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
                DB::raw('NULL as transaction_voucher_path'),
                DB::raw('NULL as payment_voucher_path'),
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
                DB::raw('NULL as transaction_id'),
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
                DB::raw('NULL as transaction_voucher_path'),
                DB::raw('NULL as payment_voucher_path'),
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
            $oldAmount = (float) $payment->amount;
            $updated = $this->payments->update($payment, $request->validated());

            // Sincronizar cambios con el cronograma vinculado
            if ($updated->schedule_id && $updated->schedule) {
                $schedule = $updated->schedule;

                // Recalcular total de TODOS los pagos para este schedule
                $totalPaid = Payment::where('schedule_id', $updated->schedule_id)->sum('amount');
                $latestPayment = Payment::where('schedule_id', $updated->schedule_id)
                    ->orderByDesc('payment_date')
                    ->first();

                $schedule->amount_paid = $totalPaid;
                $schedule->paid_date = $latestPayment->payment_date ?? $updated->payment_date;
                $schedule->payment_date = $latestPayment->payment_date ?? $updated->payment_date;
                $schedule->payment_method = $latestPayment->method ?? $updated->method;

                $schedule->status = $totalPaid >= (float) $schedule->amount
                    ? 'pagado'
                    : (($schedule->due_date && $schedule->due_date < now()->startOfDay()) ? 'vencido' : 'pendiente');

                $schedule->save();
            }

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

            // Revertir / recalcular el estado del cronograma vinculado
            if ($payment->schedule_id && $payment->schedule) {
                $schedule = $payment->schedule;

                // Verificar si hay OTROS pagos vinculados al mismo schedule
                $remainingPayments = Payment::where('schedule_id', $payment->schedule_id)
                    ->where('payment_id', '!=', $payment->payment_id)
                    ->get();

                if ($remainingPayments->isNotEmpty()) {
                    // Hay otros pagos: recalcular el monto pagado
                    $totalRemaining = $remainingPayments->sum('amount');
                    $latestPayment = $remainingPayments->sortByDesc('payment_date')->first();

                    $schedule->amount_paid = $totalRemaining;
                    $schedule->paid_date = $latestPayment->payment_date;
                    $schedule->payment_date = $latestPayment->payment_date;
                    $schedule->payment_method = $latestPayment->method;

                    // Si el monto restante cubre la cuota, mantener pagado
                    $schedule->status = $totalRemaining >= (float) $schedule->amount
                        ? 'pagado'
                        : (($schedule->due_date && $schedule->due_date < now()->startOfDay()) ? 'vencido' : 'pendiente');
                } else {
                    // No hay más pagos: revertir completamente
                    $schedule->amount_paid = null;
                    $schedule->paid_date = null;
                    $schedule->payment_date = null;
                    $schedule->payment_method = null;
                    $schedule->status = ($schedule->due_date && $schedule->due_date < now()->startOfDay())
                        ? 'vencido'
                        : 'pendiente';
                }

                $schedule->save();
            }

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

        $movementType = $request->query('movement_type');
        $method = $request->query('method');
        $hasVoucher = $request->query('has_voucher');
        $q = trim((string) $request->query('q', ''));

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        $query = $this->buildLedgerQuery($start, $end);

        if (!empty($movementType)) {
            $query->where('movement_type', $movementType);
        }

        if (!empty($method)) {
            $query->whereRaw('LOWER(COALESCE(method, "")) = ?', [strtolower(trim((string) $method))]);
        }

        if ($hasVoucher !== null && $hasVoucher !== '') {
            if ((int) $hasVoucher === 1) {
                $query->where('has_voucher', 1);
            } elseif ((int) $hasVoucher === 0) {
                $query->where('has_voucher', 0);
            }
        }

        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('client_name', 'like', $like)
                    ->orWhere('contract_number', 'like', $like)
                    ->orWhere('lot_name', 'like', $like)
                    ->orWhere('reference', 'like', $like)
                    ->orWhere('id', 'like', $like)
                    ->orWhereRaw('CAST(payment_id AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(transaction_id AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(schedule_id AS CHAR) LIKE ?', [$like])
                    ->orWhereRaw('CAST(reservation_id AS CHAR) LIKE ?', [$like]);
            });
        }

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginated,
            'meta' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'movement_type' => $movementType,
                'method' => $method,
                'has_voucher' => $hasVoucher,
                'q' => $q,
            ],
        ]);
    }

    /**
     * Reporte de clientes que han pagado al menos el 5% del valor de venta.
     * Valor de venta = total_price + COALESCE(bfh, 0)
     */
    /**
     * Query reutilizable para el reporte del 5% del valor de venta
     */
    private function buildFivePercentQuery($startDate = null, $endDate = null)
    {
        $paidByContract = DB::table('payments')
            ->select('contract_id', DB::raw('SUM(amount) as total_paid'))
            ->whereNotNull('contract_id')
            ->groupBy('contract_id');

        $schedulePaidByContract = DB::table('payment_schedules')
            ->select('contract_id', DB::raw('SUM(COALESCE(logicware_paid_amount, amount_paid, amount, 0)) as total_paid'))
            ->where('status', 'pagado')
            ->whereNotIn('schedule_id', function ($q) {
                $q->select('schedule_id')->from('payments')->whereNotNull('schedule_id');
            })
            ->groupBy('contract_id');

        $reservationDeposits = DB::table('reservations')
            ->select('reservation_id', DB::raw('COALESCE(deposit_amount, 0) as deposit'))
            ->whereNotNull('deposit_paid_at');

        $scheduleCount = DB::table('payment_schedules')
            ->select('contract_id', DB::raw('COUNT(*) as total_cuotas'), DB::raw("SUM(CASE WHEN status = 'pagado' THEN 1 ELSE 0 END) as cuotas_pagadas"))
            ->whereNull('deleted_at')
            ->groupBy('contract_id');

        return DB::table('contracts as c')
            ->leftJoin('clients as cl', 'cl.client_id', '=', DB::raw('COALESCE(c.client_id, (SELECT r.client_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))'))
            ->leftJoin('lots as l', 'l.lot_id', '=', DB::raw('COALESCE(c.lot_id, (SELECT r.lot_id FROM reservations r WHERE r.reservation_id = c.reservation_id LIMIT 1))'))
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->leftJoin('lot_financial_templates as lft', 'lft.lot_id', '=', 'l.lot_id')
            ->leftJoinSub($paidByContract, 'pp', function ($join) {
                $join->on('pp.contract_id', '=', 'c.contract_id');
            })
            ->leftJoinSub($schedulePaidByContract, 'sp', function ($join) {
                $join->on('sp.contract_id', '=', 'c.contract_id');
            })
            ->leftJoinSub($reservationDeposits, 'rd', function ($join) {
                $join->on('rd.reservation_id', '=', 'c.reservation_id');
            })
            ->leftJoinSub($scheduleCount, 'sc', function ($join) {
                $join->on('sc.contract_id', '=', 'c.contract_id');
            })
            ->where('c.status', 'vigente')
            ->where('c.total_price', '>', 0)
            ->when($startDate, fn ($q) => $q->where('c.contract_date', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('c.contract_date', '<=', $endDate))
            ->select(
                'c.contract_id',
                'c.contract_number',
                DB::raw('CONCAT(COALESCE(cl.first_name, ""), " ", COALESCE(cl.last_name, "")) as client_name'),
                'cl.client_id',
                'cl.primary_phone as client_phone',
                'cl.email as client_email',
                DB::raw('CONCAT(COALESCE(m.name, ""), " - Lote ", COALESCE(l.num_lot, "")) as lot_name'),
                DB::raw('COALESCE(lft.precio_venta, c.total_price) as precio_venta'),
                DB::raw('COALESCE(c.discount, 0) as descuento'),
                DB::raw('COALESCE(lft.bono_techo_propio, 0) as bono_techo_propio'),
                DB::raw('(COALESCE(lft.precio_venta, c.total_price) - COALESCE(c.discount, 0) + COALESCE(lft.bono_techo_propio, 0)) as precio_total_real'),
                DB::raw('ROUND((COALESCE(lft.precio_venta, c.total_price) - COALESCE(c.discount, 0) + COALESCE(lft.bono_techo_propio, 0)) * 0.05, 2) as five_percent_threshold'),
                DB::raw('(COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) as total_paid'),
                DB::raw('ROUND(
                    CASE WHEN (COALESCE(lft.precio_venta, c.total_price) - COALESCE(c.discount, 0) + COALESCE(lft.bono_techo_propio, 0)) > 0
                    THEN ((COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0)) / (COALESCE(lft.precio_venta, c.total_price) - COALESCE(c.discount, 0) + COALESCE(lft.bono_techo_propio, 0))) * 100
                    ELSE 0 END
                , 2) as paid_percentage'),
                DB::raw('ROUND(
                    CASE WHEN (COALESCE(lft.precio_venta, c.total_price) - COALESCE(c.discount, 0) + COALESCE(lft.bono_techo_propio, 0)) > 0
                    THEN ((COALESCE(pp.total_paid, 0) + COALESCE(sp.total_paid, 0) + COALESCE(rd.deposit, 0) + COALESCE(lft.bono_techo_propio, 0)) / (COALESCE(lft.precio_venta, c.total_price) - COALESCE(c.discount, 0) + COALESCE(lft.bono_techo_propio, 0))) * 100
                    ELSE 0 END
                , 2) as paid_percentage_with_bono'),
                DB::raw('COALESCE(sc.cuotas_pagadas, 0) as cuotas_pagadas'),
                DB::raw('COALESCE(sc.total_cuotas, 0) as total_cuotas')
            )
            ->havingRaw('total_paid >= five_percent_threshold')
            ->orderByDesc('paid_percentage');
    }

    /**
     * Reporte JSON de clientes que han pagado al menos el 5% del valor de venta.
     */
    public function fivePercentReport(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $results = $this->buildFivePercentQuery($startDate, $endDate)->get()->map(fn ($r) => (array) $r)->all();

        $totalQuery = DB::table('contracts')
            ->where('status', 'vigente')
            ->where('total_price', '>', 0);
        if ($startDate) $totalQuery->where('contract_date', '>=', $startDate);
        if ($endDate) $totalQuery->where('contract_date', '<=', $endDate);
        $totalContracts = $totalQuery->count();

        return response()->json([
            'success' => true,
            'data' => [
                'clients_reached_5_percent' => count($results),
                'total_active_contracts' => $totalContracts,
                'percentage_reached' => $totalContracts > 0 ? round((count($results) / $totalContracts) * 100, 1) : 0,
                'total_collected_from_qualified' => round(array_sum(array_column($results, 'total_paid')), 2),
                'clients' => $results,
            ],
        ]);
    }

    /**
     * Exportar a Excel el reporte de clientes que alcanzaron el 5% del valor de venta.
     */
    public function exportFivePercentReport(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $results = $this->buildFivePercentQuery($startDate, $endDate)->get();

        $totalQuery = DB::table('contracts')
            ->where('status', 'vigente')
            ->where('total_price', '>', 0);
        if ($startDate) $totalQuery->where('contract_date', '>=', $startDate);
        if ($endDate) $totalQuery->where('contract_date', '<=', $endDate);
        $totalContracts = $totalQuery->count();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte 5%');

        // --- Encabezado del reporte ---
        $sheet->mergeCells('A1:J1');
        $sheet->setCellValue('A1', 'REPORTE: Clientes que alcanzaron el 5% del Valor de Venta');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('A2:J2');
        $sheet->setCellValue('A2', 'Fecha de generación: ' . now()->format('d/m/Y H:i') . '  |  Precio Total Real = Precio Financiado + Bono Techo Propio  |  5% = solo dinero cobrado (sin bono)');
        $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // --- KPIs resumen ---
        $sheet->setCellValue('A4', 'Clientes que alcanzaron 5%:');
        $sheet->setCellValue('C4', count($results));
        $sheet->setCellValue('D4', 'de ' . $totalContracts . ' contratos vigentes');
        $sheet->setCellValue('F4', 'Total cobrado:');
        $sheet->setCellValue('G4', $results->sum('total_paid'));
        $sheet->getStyle('G4')->getNumberFormat()->setFormatCode('"S/" #,##0.00');
        $sheet->getStyle('A4:G4')->getFont()->setBold(true);

        // --- Headers de tabla ---
        $headerRow = 6;
        $headers = [
            'A' => 'N°',
            'B' => 'Cliente',
            'C' => 'Teléfono',
            'D' => 'Email',
            'E' => 'Contrato',
            'F' => 'Lote',
            'G' => 'Precio Venta (S/)',
            'H' => 'Descuento (S/)',
            'I' => 'Bono Techo Propio (S/)',
            'J' => 'Precio Total Real (S/)',
            'K' => '5% Requerido (S/)',
            'L' => 'Total Pagado (S/)',
            'M' => '% Pagado (sin Bono)',
            'N' => '% Pagado (con Bono)',
            'O' => 'Cuotas Pagadas',
            'P' => 'Total Cuotas',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F766E']]],
        ];
        $sheet->getStyle("A{$headerRow}:P{$headerRow}")->applyFromArray($headerStyle);
        $sheet->getRowDimension($headerRow)->setRowHeight(28);

        // --- Datos ---
        $row = $headerRow + 1;
        $num = 1;
        foreach ($results as $client) {
            $sheet->setCellValue("A{$row}", $num);
            $sheet->setCellValue("B{$row}", trim($client->client_name));
            $sheet->setCellValue("C{$row}", $client->client_phone ?? '—');
            $sheet->setCellValue("D{$row}", $client->client_email ?? '—');
            $sheet->setCellValue("E{$row}", $client->contract_number ?? $client->contract_id);
            $sheet->setCellValue("F{$row}", $client->lot_name ?? '—');
            $sheet->setCellValue("G{$row}", (float) $client->precio_venta);
            $sheet->setCellValue("H{$row}", (float) ($client->descuento ?? 0));
            $sheet->setCellValue("I{$row}", (float) $client->bono_techo_propio);
            $sheet->setCellValue("J{$row}", (float) $client->precio_total_real);
            $sheet->setCellValue("K{$row}", (float) $client->five_percent_threshold);
            $sheet->setCellValue("L{$row}", (float) $client->total_paid);
            $sheet->setCellValue("M{$row}", (float) $client->paid_percentage / 100);
            $sheet->setCellValue("N{$row}", (float) $client->paid_percentage_with_bono / 100);
            $sheet->setCellValue("O{$row}", (int) $client->cuotas_pagadas);
            $sheet->setCellValue("P{$row}", (int) $client->total_cuotas);

            // Zebra striping
            if ($num % 2 === 0) {
                $sheet->getStyle("A{$row}:P{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F0FDFA');
            }

            $row++;
            $num++;
        }

        // Formato moneda y porcentaje
        $lastDataRow = $row - 1;
        if ($lastDataRow >= $headerRow + 1) {
            $moneyRange = "G" . ($headerRow + 1) . ":L{$lastDataRow}";
            $sheet->getStyle($moneyRange)->getNumberFormat()->setFormatCode('"S/" #,##0.00');
            $percentRange = "M" . ($headerRow + 1) . ":N{$lastDataRow}";
            $sheet->getStyle($percentRange)->getNumberFormat()->setFormatCode('0.00%');
        }

        // Fila de totales
        $totalRow = $row + 1;
        $sheet->setCellValue("A{$totalRow}", 'TOTALES');
        $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
        $sheet->getStyle("A{$totalRow}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        if ($lastDataRow >= $headerRow + 1) {
            $sheet->setCellValue("G{$totalRow}", "=SUM(G" . ($headerRow + 1) . ":G{$lastDataRow})");
            $sheet->setCellValue("H{$totalRow}", "=SUM(H" . ($headerRow + 1) . ":H{$lastDataRow})");
            $sheet->setCellValue("I{$totalRow}", "=SUM(I" . ($headerRow + 1) . ":I{$lastDataRow})");
            $sheet->setCellValue("J{$totalRow}", "=SUM(J" . ($headerRow + 1) . ":J{$lastDataRow})");
            $sheet->setCellValue("K{$totalRow}", "=SUM(K" . ($headerRow + 1) . ":K{$lastDataRow})");
            $sheet->setCellValue("L{$totalRow}", "=SUM(L" . ($headerRow + 1) . ":L{$lastDataRow})");
        }
        $sheet->getStyle("G{$totalRow}:L{$totalRow}")->getNumberFormat()->setFormatCode('"S/" #,##0.00');
        $sheet->getStyle("A{$totalRow}:P{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:P{$totalRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE);

        // Bordes para toda la tabla de datos
        if ($lastDataRow >= $headerRow + 1) {
            $sheet->getStyle("A{$headerRow}:P{$lastDataRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
        }

        // Auto-tamaño de columnas
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ====== HOJA LEYENDA ======
        $legendSheet = $spreadsheet->createSheet();
        $legendSheet->setTitle('Leyenda');

        // Título
        $legendSheet->mergeCells('A1:C1');
        $legendSheet->setCellValue('A1', 'LEYENDA DE COLUMNAS');
        $legendSheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('1E3A8A');
        $legendSheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $legendSheet->getRowDimension(1)->setRowHeight(28);

        // Subtítulo
        $legendSheet->mergeCells('A2:C2');
        $legendSheet->setCellValue('A2', 'Generado: ' . now()->format('d/m/Y H:i'));
        $legendSheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true)->getColor()->setRGB('6B7280');
        $legendSheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Headers de leyenda
        $lRow = 4;
        $legendSheet->setCellValue("A{$lRow}", 'Columna');
        $legendSheet->setCellValue("B{$lRow}", 'Descripción');
        $legendSheet->setCellValue("C{$lRow}", 'Tipo / Fórmula');
        $legendSheet->getStyle("A{$lRow}:C{$lRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        $legendItems = [
            ['N°', 'Número correlativo', 'Número'],
            ['Cliente', 'Nombre completo del cliente', 'Texto'],
            ['Teléfono', 'Teléfono principal de contacto', 'Texto'],
            ['Email', 'Correo electrónico del cliente', 'Texto'],
            ['Contrato', 'Número de contrato', 'Texto'],
            ['Lote', 'Identificador del lote (Manzana - Lote)', 'Texto'],
            ['Precio Venta (S/)', 'Precio de lista del lote (sin descuento, sin bono)', 'Moneda S/'],
            ['Descuento (S/)', 'Descuento aplicado al contrato (proveniente del CRM)', 'Moneda S/'],
            ['Bono Techo Propio (S/)', 'Subsidio estatal Techo Propio (S/ 52,250 si aplica)', 'Moneda S/'],
            ['Precio Total Real (S/)', 'Precio final = Precio Venta − Descuento + Bono Techo Propio', 'Moneda S/ (fórmula)'],
            ['5% Requerido (S/)', 'Umbral del 5% sobre Precio Total Real para separación', 'Moneda S/ (fórmula)'],
            ['Total Pagado (S/)', 'Suma de cuotas pagadas (solo dinero cobrado, sin bono)', 'Moneda S/'],
            ['% Pagado (sin Bono)', 'Porcentaje pagado = Total Pagado / Precio Total Real × 100', 'Porcentaje (fórmula)'],
            ['% Pagado (con Bono)', 'Porcentaje incluyendo bono = (Total Pagado + Bono) / Precio Total Real × 100', 'Porcentaje (fórmula)'],
            ['Cuotas Pagadas', 'Cantidad de cuotas con estado "pagado"', 'Número'],
            ['Total Cuotas', 'Total de cuotas en el cronograma', 'Número'],
        ];

        $lRow++;
        foreach ($legendItems as $i => $item) {
            $legendSheet->setCellValue("A{$lRow}", $item[0]);
            $legendSheet->setCellValue("B{$lRow}", $item[1]);
            $legendSheet->setCellValue("C{$lRow}", $item[2]);
            if ($i % 2 === 0) {
                $legendSheet->getStyle("A{$lRow}:C{$lRow}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0FDFA');
            }
            $lRow++;
        }

        // Bordes
        $legendSheet->getStyle("A4:C" . ($lRow - 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');

        // Fórmulas clave
        $lRow += 1;
        $legendSheet->setCellValue("A{$lRow}", 'FÓRMULAS CLAVE:');
        $legendSheet->getStyle("A{$lRow}")->getFont()->setBold(true)->setSize(11);
        $lRow++;
        $formulas = [
            'Precio Total Real = Precio Venta − Descuento + Bono Techo Propio',
            '5% Requerido = Precio Total Real × 0.05',
            '% Pagado (sin Bono) = Total Pagado ÷ Precio Total Real × 100',
            '% Pagado (con Bono) = (Total Pagado + Bono) ÷ Precio Total Real × 100',
            'Solo aparecen contratos donde Total Pagado ≥ 5% Requerido',
        ];
        foreach ($formulas as $f) {
            $legendSheet->setCellValue("B{$lRow}", $f);
            $legendSheet->getStyle("B{$lRow}")->getFont()->setItalic(true)->getColor()->setRGB('4B5563');
            $lRow++;
        }

        // Anchos
        $legendSheet->getColumnDimension('A')->setWidth(24);
        $legendSheet->getColumnDimension('B')->setWidth(60);
        $legendSheet->getColumnDimension('C')->setWidth(24);

        // Volver a la primera hoja como activa
        $spreadsheet->setActiveSheetIndex(0);

        // Generar y descargar
        $fileName = 'Reporte_5_Porciento_' . now()->format('Y-m-d_His') . '.xlsx';
        $tempPath = storage_path('app/temp/' . $fileName);

        if (!is_dir(dirname($tempPath))) {
            mkdir(dirname($tempPath), 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return response()->download($tempPath, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function summary(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        $items = $this->buildLedgerQuery($start, $end)->limit(5000)->get()->map(fn ($r) => (array) $r)->all();

        $totalAmount = 0.0;
        $cashAmount = 0.0;
        $bankAmount = 0.0;

        $cashMethods = ['cash', 'efectivo'];
        $bankMethods = ['transfer', 'transferencia', 'card', 'tarjeta', 'check', 'cheque', 'yape', 'plin'];

        foreach ($items as $it) {
            $amount = (float) ($it['amount'] ?? 0);
            $totalAmount += $amount;

            $rawMethod = (string) ($it['method'] ?? '');
            $method = strtolower(trim($rawMethod));
            if (in_array($method, $cashMethods, true)) {
                $cashAmount += $amount;
            } elseif (in_array($method, $bankMethods, true)) {
                $bankAmount += $amount;
            }
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
        $topFromLedger = array_slice($items, 0, 10);

        $overdueBase = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->where('c.status', 'vigente')
            ->where('ps.status', '!=', 'pagado')
            ->whereNotNull('ps.due_date')
            ->whereDate('ps.due_date', '<=', $end->toDateString())
            ->where(function ($q) {
                $q->whereNull('ps.type')->orWhere('ps.type', '!=', 'bono_bpp');
            });

        $dueSoonLimit = $end->copy()->addDays(7)->toDateString();
        $dueSoonBase = DB::table('payment_schedules as ps')
            ->join('contracts as c', 'ps.contract_id', '=', 'c.contract_id')
            ->where('c.status', 'vigente')
            ->where('ps.status', '!=', 'pagado')
            ->whereNotNull('ps.due_date')
            ->whereDate('ps.due_date', '>', $end->toDateString())
            ->whereDate('ps.due_date', '<=', $dueSoonLimit)
            ->where(function ($q) {
                $q->whereNull('ps.type')->orWhere('ps.type', '!=', 'bono_bpp');
            });

        $paidBySchedule = DB::table('payments')
            ->select('schedule_id', DB::raw('SUM(amount) as paid_amount'))
            ->whereNotNull('schedule_id')
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->groupBy('schedule_id');

        $overdueRemainingBase = (clone $overdueBase)
            ->leftJoinSub($paidBySchedule, 'pay', 'pay.schedule_id', '=', 'ps.schedule_id')
            ->selectRaw('ps.schedule_id, GREATEST(ps.amount - COALESCE(pay.paid_amount, 0), 0) as remaining_amount');

        $overdueAgg = DB::query()
            ->fromSub($overdueRemainingBase, 't')
            ->where('t.remaining_amount', '>', 0)
            ->selectRaw('COUNT(*) as cnt, SUM(t.remaining_amount) as amt')
            ->first();

        $dueSoonRemainingBase = (clone $dueSoonBase)
            ->leftJoinSub($paidBySchedule, 'pay', 'pay.schedule_id', '=', 'ps.schedule_id')
            ->selectRaw('ps.schedule_id, GREATEST(ps.amount - COALESCE(pay.paid_amount, 0), 0) as remaining_amount');

        $dueSoonAgg = DB::query()
            ->fromSub($dueSoonRemainingBase, 't')
            ->where('t.remaining_amount', '>', 0)
            ->selectRaw('COUNT(*) as cnt, SUM(t.remaining_amount) as amt')
            ->first();

        $topTransactions = DB::table('payment_transactions as pt')
            ->leftJoin('contracts as c', 'pt.contract_id', '=', 'c.contract_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('clients as cl', function ($join) {
                $join->on('cl.client_id', '=', DB::raw('COALESCE(c.client_id, r.client_id)'));
            })
            ->leftJoin('lots as l', function ($join) {
                $join->on('l.lot_id', '=', DB::raw('COALESCE(c.lot_id, r.lot_id)'));
            })
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->whereBetween('pt.payment_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('pt.amount_total')
            ->limit(10)
            ->get([
                DB::raw('pt.transaction_id as transaction_id'),
                DB::raw('NULL as payment_id'),
                DB::raw('NULL as reservation_id'),
                DB::raw('NULL as schedule_id'),
                'c.contract_id',
                'c.contract_number',
                DB::raw('COALESCE(c.client_id, r.client_id) as client_id'),
                DB::raw('CONCAT(COALESCE(cl.first_name, ""), " ", COALESCE(cl.last_name, "")) as client_name'),
                DB::raw('COALESCE(c.lot_id, r.lot_id) as lot_id'),
                DB::raw('CONCAT(COALESCE(m.name, ""), " - Lote ", COALESCE(l.num_lot, "")) as lot_name'),
                DB::raw('pt.payment_date as date'),
                DB::raw('pt.amount_total as amount'),
                DB::raw('pt.method as method'),
                DB::raw('pt.reference as reference'),
                DB::raw("CASE WHEN pt.voucher_path IS NULL OR pt.voucher_path = '' THEN 0 ELSE 1 END as has_voucher"),
            ])
            ->map(fn ($r) => (array) $r)
            ->all();

        $legacyTop = [];
        if (count($topTransactions) < 10) {
            foreach ($items as $it) {
                if (($it['source'] ?? null) !== 'payment') continue;
                if (!empty($it['transaction_id'] ?? null)) continue;
                $legacyTop[] = $it;
                if ((count($topTransactions) + count($legacyTop)) >= 10) break;
            }
        }

        $topPayments = array_slice(array_merge($topTransactions, $legacyTop, $topFromLedger), 0, 10);

        $summaryData = [
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ],
            'total_payments_count' => count($items),
            'total_payments_received' => round($totalAmount, 2),
            'payments_by_method' => array_values($byMethod),
            'payments_by_type' => array_values($byType),
            'top_payments' => $topPayments,
            'collections_alerts' => [
                'overdue' => [
                    'count' => (int) ($overdueAgg->cnt ?? 0),
                    'amount' => round((float) ($overdueAgg->amt ?? 0), 2),
                ],
                'due_soon' => [
                    'days' => 7,
                    'count' => (int) ($dueSoonAgg->cnt ?? 0),
                    'amount' => round((float) ($dueSoonAgg->amt ?? 0), 2),
                ],
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $summaryData['period'],
                'total_payments_count' => $summaryData['total_payments_count'],
                'total_payments_received' => $summaryData['total_payments_received'],
                'cash_balance' => round($cashAmount, 2),
                'bank_balance' => round($bankAmount, 2),
                'summary_data' => $summaryData,
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

    /**
     * Actualizar datos de pago de un schedule (cuotas de Logicware/cronograma)
     */
    public function updateSchedulePayment(Request $request, $scheduleId)
    {
        $schedule = PaymentSchedule::findOrFail($scheduleId);

        $validated = $request->validate([
            'amount'       => 'sometimes|numeric|min:0',
            'payment_date' => 'sometimes|date',
            'method'       => 'sometimes|nullable|string|max:60',
            'reference'    => 'sometimes|nullable|string|max:60',
        ]);

        try {
            DB::beginTransaction();

            if (isset($validated['amount'])) {
                $schedule->amount_paid = $validated['amount'];
                $schedule->logicware_paid_amount = $validated['amount'];
            }
            if (isset($validated['payment_date'])) {
                $schedule->paid_date = $validated['payment_date'];
                $schedule->payment_date = $validated['payment_date'];
            }
            if (array_key_exists('method', $validated)) {
                $schedule->payment_method = $validated['method'];
            }
            if (array_key_exists('reference', $validated)) {
                $schedule->notes = $validated['reference'];
            }

            // Recalcular status
            $paidAmount = (float) ($schedule->amount_paid ?? 0);
            if ($paidAmount >= (float) $schedule->amount) {
                $schedule->status = 'pagado';
            } else {
                $schedule->status = ($schedule->due_date && $schedule->due_date < now()->startOfDay())
                    ? 'vencido'
                    : 'pendiente';
            }

            $schedule->save();
            DB::commit();

            return response()->json(['message' => 'Cuota actualizada correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar cuota',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Revertir un schedule pagado a pendiente/vencido (eliminar datos de pago)
     */
    public function revertSchedule($scheduleId)
    {
        $schedule = PaymentSchedule::findOrFail($scheduleId);

        if ($schedule->status !== 'pagado') {
            return response()->json(['message' => 'Esta cuota no está marcada como pagada'], 422);
        }

        try {
            DB::beginTransaction();

            // Verificar si hay payments vinculados a este schedule
            $linkedPayments = Payment::where('schedule_id', $schedule->schedule_id)->count();
            if ($linkedPayments > 0) {
                DB::rollBack();
                return response()->json([
                    'message' => 'No se puede revertir: hay pagos manuales vinculados a esta cuota. Elimine los pagos primero.',
                ], 422);
            }

            $schedule->amount_paid = null;
            $schedule->paid_date = null;
            $schedule->payment_date = null;
            $schedule->payment_method = null;
            $schedule->logicware_paid_amount = null;
            $schedule->notes = null;
            $schedule->status = ($schedule->due_date && $schedule->due_date < now()->startOfDay())
                ? 'vencido'
                : 'pendiente';
            $schedule->save();

            DB::commit();

            return response()->json(['message' => 'Cuota revertida correctamente']);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al revertir cuota',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
