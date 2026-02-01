<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\Collections\app\Models\Followup;
use Modules\Collections\app\Models\FollowupLog;
use Modules\Sales\Models\PaymentSchedule;

class KpisController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $employeeId = $request->filled('employee_id') ? $request->integer('employee_id') : null;

        $start = $startDate ? Carbon::parse($startDate)->startOfDay() : now()->startOfMonth();
        $end = $endDate ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();
        $today = Carbon::today();
        $due7 = $today->copy()->addDays(7);

        // Estado de seguimientos
        $followupsBase = Followup::query();
        if ($employeeId) {
            $followupsBase->where('assigned_employee_id', $employeeId);
        }
        $totalFollowups = (clone $followupsBase)->count();
        $overdue = (clone $followupsBase)->where('overdue_installments', '>', 0)->count();
        $resolved = (clone $followupsBase)->where('management_status', 'resolved')->count();
        $inProgress = (clone $followupsBase)->where('management_status', 'in_progress')->count();
        
        // Actividad de gestión
        $logsBase = FollowupLog::query();
        if ($employeeId) {
            $logsBase->where('employee_id', $employeeId);
        }
        $logsBase->where(function ($q) use ($start, $end) {
            $q->whereBetween('logged_at', [$start, $end])
              ->orWhere(function ($q2) use ($start, $end) {
                  $q2->whereNull('logged_at')->whereBetween('created_at', [$start, $end]);
              });
        });
        $totalLogs = (clone $logsBase)->count();
        $callsCount = (clone $logsBase)->where('channel', 'call')->count();
        $whatsappCount = (clone $logsBase)->where('channel', 'whatsapp')->count();
        $emailCount = (clone $logsBase)->where('channel', 'email')->count();
        $smsCount = (clone $logsBase)->where('channel', 'sms')->count();
        $letterCount = (clone $logsBase)->where('channel', 'letter')->count();
        $paymentCount = (clone $logsBase)->where('channel', 'payment')->count();
        $commitmentLogsCount = (clone $logsBase)->where('channel', 'commitment')->count();
        
        // Efectividad de contacto (basado en el resultado)
        $contactedCount = (clone $logsBase)->where('result', 'contacted')->count();
        $unreachableCount = (clone $logsBase)->whereIn('result', ['not_reached', 'unreachable'])->count();
        $resolvedByLogCount = (clone $logsBase)->where('result', 'resolved')->count();
        $fulfilledByLogCount = (clone $logsBase)->where('result', 'fulfilled')->count();
        
        // Compromisos de pago
        $commitmentsTotal = (clone $followupsBase)->whereNotNull('commitment_date')->count();
        $commitmentsFulfilled = (clone $followupsBase)->where('commitment_status', 'fulfilled')->count();
        $commitmentsPending = (clone $followupsBase)->where('commitment_status', 'pending')->count();
        $commitmentsOverdue = (clone $followupsBase)->where('commitment_status', 'pending')->whereNotNull('commitment_date')->where('commitment_date', '<', $today)->count();
        $commitmentsDue7d = (clone $followupsBase)->where('commitment_status', 'pending')->whereNotNull('commitment_date')->whereBetween('commitment_date', [$today->toDateString(), $due7->toDateString()])->count();
        
        // Métricas financieras
        $totalAmount = (clone $followupsBase)->sum('pending_amount') ?? 0;
        
        // Monto recuperado en el periodo (pagos realizados en cuotas de seguimientos activos)
        $recoveredQuery = DB::table('payment_schedules as ps')
            ->join('collection_followups as cf', 'cf.contract_id', '=', 'ps.contract_id')
            ->where('ps.status', 'pagado')
            ->whereBetween('ps.paid_date', [$start->toDateTimeString(), $end->toDateTimeString()]);
        if ($employeeId) {
            $recoveredQuery->where('cf.assigned_employee_id', $employeeId);
        }
        $recoveredAmount = $recoveredQuery->sum('ps.amount') ?? 0;

        $team = null;
        if (!$employeeId) {
            $team = $this->buildTeamBreakdown($start, $end, $today, $due7);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'scope' => $employeeId ? 'employee' : 'global',
                'employee_id' => $employeeId,
                'period' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                ],
                // Estado de seguimientos
                'total_followups' => $totalFollowups,
                'overdue_followups' => $overdue,
                'resolved_followups' => $resolved,
                'in_progress_followups' => $inProgress,
                
                // Actividad de gestión
                'total_logs' => $totalLogs,
                'calls_count' => $callsCount,
                'whatsapp_count' => $whatsappCount,
                'email_count' => $emailCount,
                'sms_count' => $smsCount,
                'letter_count' => $letterCount,
                'payment_count' => $paymentCount,
                'commitment_logs_count' => $commitmentLogsCount,
                
                // Efectividad de contacto
                'contacted_count' => $contactedCount,
                'unreachable_count' => $unreachableCount,
                'resolved_by_log_count' => $resolvedByLogCount,
                'fulfilled_by_log_count' => $fulfilledByLogCount,
                
                // Compromisos
                'commitments_total' => $commitmentsTotal,
                'commitments_fulfilled' => $commitmentsFulfilled,
                'commitments_pending' => $commitmentsPending,
                'commitments_overdue' => $commitmentsOverdue,
                'commitments_due_7d' => $commitmentsDue7d,
                
                // Financiero
                'total_amount' => round($totalAmount, 2),
                'recovered_amount' => round($recoveredAmount, 2),

                'team' => $team,
            ]
        ]);
    }

    private function buildTeamBreakdown(Carbon $start, Carbon $end, Carbon $today, Carbon $due7): array
    {
        $logAgg = DB::table('collection_followup_logs as l')
            ->whereNotNull('l.employee_id')
            ->where(function ($q) use ($start, $end) {
                $q->whereBetween('l.logged_at', [$start, $end])
                  ->orWhere(function ($q2) use ($start, $end) {
                      $q2->whereNull('l.logged_at')->whereBetween('l.created_at', [$start, $end]);
                  });
            })
            ->groupBy('l.employee_id')
            ->selectRaw('l.employee_id as employee_id')
            ->selectRaw('COUNT(*) as total_logs')
            ->selectRaw("SUM(CASE WHEN l.channel='call' THEN 1 ELSE 0 END) as calls_count")
            ->selectRaw("SUM(CASE WHEN l.channel='whatsapp' THEN 1 ELSE 0 END) as whatsapp_count")
            ->selectRaw("SUM(CASE WHEN l.channel='sms' THEN 1 ELSE 0 END) as sms_count")
            ->selectRaw("SUM(CASE WHEN l.channel='email' THEN 1 ELSE 0 END) as email_count")
            ->selectRaw("SUM(CASE WHEN l.channel='letter' THEN 1 ELSE 0 END) as letter_count")
            ->selectRaw("SUM(CASE WHEN l.channel='payment' THEN 1 ELSE 0 END) as payment_count")
            ->selectRaw("SUM(CASE WHEN l.channel='commitment' THEN 1 ELSE 0 END) as commitment_logs_count")
            ->selectRaw("SUM(CASE WHEN l.result='contacted' THEN 1 ELSE 0 END) as contacted_count")
            ->selectRaw("SUM(CASE WHEN l.result IN ('not_reached','unreachable') THEN 1 ELSE 0 END) as unreachable_count")
            ->selectRaw("SUM(CASE WHEN l.result='resolved' THEN 1 ELSE 0 END) as resolved_by_log_count")
            ->selectRaw("SUM(CASE WHEN l.result='fulfilled' THEN 1 ELSE 0 END) as fulfilled_by_log_count")
            ->get()
            ->keyBy('employee_id');

        $followupAgg = DB::table('collection_followups as f')
            ->whereNotNull('f.assigned_employee_id')
            ->groupBy('f.assigned_employee_id')
            ->selectRaw('f.assigned_employee_id as employee_id')
            ->selectRaw('COUNT(*) as assigned_followups')
            ->selectRaw("SUM(CASE WHEN f.overdue_installments > 0 THEN 1 ELSE 0 END) as overdue_followups")
            ->selectRaw("SUM(CASE WHEN f.management_status = 'resolved' THEN 1 ELSE 0 END) as resolved_followups")
            ->selectRaw("SUM(CASE WHEN f.management_status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_followups")
            ->selectRaw("SUM(CASE WHEN f.commitment_status = 'pending' THEN 1 ELSE 0 END) as commitments_pending")
            ->selectRaw("SUM(CASE WHEN f.commitment_status = 'pending' AND f.commitment_date IS NOT NULL AND f.commitment_date < ? THEN 1 ELSE 0 END) as commitments_overdue", [$today->toDateString()])
            ->selectRaw("SUM(CASE WHEN f.commitment_status = 'pending' AND f.commitment_date IS NOT NULL AND f.commitment_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as commitments_due_7d", [$today->toDateString(), $due7->toDateString()])
            ->selectRaw('COALESCE(SUM(f.pending_amount), 0) as pending_amount')
            ->get()
            ->keyBy('employee_id');

        $recoveredAgg = DB::table('payment_schedules as ps')
            ->join('collection_followups as cf', 'cf.contract_id', '=', 'ps.contract_id')
            ->where('ps.status', 'pagado')
            ->whereBetween('ps.paid_date', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereNotNull('cf.assigned_employee_id')
            ->groupBy('cf.assigned_employee_id')
            ->selectRaw('cf.assigned_employee_id as employee_id')
            ->selectRaw('COALESCE(SUM(ps.amount), 0) as recovered_amount')
            ->get()
            ->keyBy('employee_id');

        $employeeIds = collect()
            ->merge($logAgg->keys())
            ->merge($followupAgg->keys())
            ->merge($recoveredAgg->keys())
            ->unique()
            ->values();

        if ($employeeIds->isEmpty()) return [];

        $employees = DB::table('employees as e')
            ->leftJoin('users as u', 'u.user_id', '=', 'e.user_id')
            ->whereIn('e.employee_id', $employeeIds->all())
            ->selectRaw("e.employee_id as employee_id")
            ->selectRaw("TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))) as employee_name")
            ->get()
            ->keyBy('employee_id');

        $team = [];
        foreach ($employeeIds as $id) {
            $emp = $employees->get($id);
            $logs = $logAgg->get($id);
            $fol = $followupAgg->get($id);
            $rec = $recoveredAgg->get($id);
            $team[] = [
                'employee_id' => (int) $id,
                'employee_name' => $emp?->employee_name ?: ('Empleado #' . $id),
                'assigned_followups' => (int) ($fol->assigned_followups ?? 0),
                'overdue_followups' => (int) ($fol->overdue_followups ?? 0),
                'in_progress_followups' => (int) ($fol->in_progress_followups ?? 0),
                'resolved_followups' => (int) ($fol->resolved_followups ?? 0),
                'pending_amount' => round((float) ($fol->pending_amount ?? 0), 2),
                'commitments_pending' => (int) ($fol->commitments_pending ?? 0),
                'commitments_overdue' => (int) ($fol->commitments_overdue ?? 0),
                'commitments_due_7d' => (int) ($fol->commitments_due_7d ?? 0),
                'total_logs' => (int) ($logs->total_logs ?? 0),
                'calls_count' => (int) ($logs->calls_count ?? 0),
                'whatsapp_count' => (int) ($logs->whatsapp_count ?? 0),
                'sms_count' => (int) ($logs->sms_count ?? 0),
                'email_count' => (int) ($logs->email_count ?? 0),
                'letter_count' => (int) ($logs->letter_count ?? 0),
                'payment_count' => (int) ($logs->payment_count ?? 0),
                'commitment_logs_count' => (int) ($logs->commitment_logs_count ?? 0),
                'contacted_count' => (int) ($logs->contacted_count ?? 0),
                'unreachable_count' => (int) ($logs->unreachable_count ?? 0),
                'resolved_by_log_count' => (int) ($logs->resolved_by_log_count ?? 0),
                'fulfilled_by_log_count' => (int) ($logs->fulfilled_by_log_count ?? 0),
                'recovered_amount' => round((float) ($rec->recovered_amount ?? 0), 2),
            ];
        }

        usort($team, function ($a, $b) {
            if ($b['recovered_amount'] !== $a['recovered_amount']) return $b['recovered_amount'] <=> $a['recovered_amount'];
            if ($b['contacted_count'] !== $a['contacted_count']) return $b['contacted_count'] <=> $a['contacted_count'];
            return $b['total_logs'] <=> $a['total_logs'];
        });

        return $team;
    }
}
