<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class KpisController extends Controller
{
    public function index(Request $request)
    {
        $employeeId = $request->integer('employee_id');
        $fresh = (bool) $request->boolean('fresh', false);

        [$periodStart, $periodEnd] = $this->resolvePeriod($request);
        $month = $periodStart->month;
        $year = $periodStart->year;

        $cacheKey = 'collections:kpis:v3:' . sha1(json_encode([
            'employee_id' => $employeeId,
            'start' => $periodStart->toDateTimeString(),
            'end' => $periodEnd->toDateTimeString(),
        ]));

        $payload = $fresh
            ? $this->computeKpis($employeeId, $periodStart, $periodEnd)
            : Cache::remember($cacheKey, 60, fn () => $this->computeKpis($employeeId, $periodStart, $periodEnd));

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    private function resolvePeriod(Request $request): array
    {
        $start = $request->input('start') ?? $request->input('date_from');
        $end = $request->input('end') ?? $request->input('date_to');

        if ($start && $end) {
            $periodStart = Carbon::parse($start)->startOfDay();
            $periodEnd = Carbon::parse($end)->endOfDay();
            return [$periodStart, $periodEnd];
        }

        $month = $request->integer('month') ?: Carbon::now()->month;
        $year = $request->integer('year') ?: Carbon::now()->year;

        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = Carbon::create($year, $month, 1)->endOfMonth();
        return [$periodStart, $periodEnd];
    }

    private function computeKpis(?int $employeeId, Carbon $periodStart, Carbon $periodEnd): array
    {
        $today = Carbon::today();

        $followupsBase = DB::table('collection_followups');
        if ($employeeId) {
            $followupsBase->where('assigned_employee_id', $employeeId);
        }

        $portfolioAgg = (clone $followupsBase)->selectRaw('
            COUNT(*) as total_followups,
            SUM(CASE WHEN overdue_installments > 0 THEN 1 ELSE 0 END) as overdue_followups,
            SUM(CASE WHEN management_status = ? THEN 1 ELSE 0 END) as resolved_followups,
            SUM(CASE WHEN management_status = ? THEN 1 ELSE 0 END) as in_progress_followups,
            COALESCE(SUM(pending_amount), 0) as total_amount
        ', ['resolved', 'in_progress'])->first();

        $periodFollowupsAgg = (clone $followupsBase)->selectRaw('
            SUM(CASE WHEN due_date IS NOT NULL AND due_date >= ? AND due_date <= ? THEN 1 ELSE 0 END) as due_in_period,
            SUM(CASE WHEN due_date IS NOT NULL AND due_date >= ? AND due_date <= ? AND overdue_installments > 0 THEN 1 ELSE 0 END) as overdue_in_period,
            SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) as new_cases_in_period,
            SUM(CASE WHEN commitment_date IS NOT NULL AND commitment_date >= ? AND commitment_date <= ? THEN 1 ELSE 0 END) as commitments_total,
            SUM(CASE WHEN commitment_status = ? AND commitment_date IS NOT NULL AND commitment_date >= ? AND commitment_date <= ? THEN 1 ELSE 0 END) as commitments_fulfilled,
            SUM(CASE WHEN commitment_status = ? AND commitment_date IS NOT NULL AND commitment_date >= ? AND commitment_date <= ? THEN 1 ELSE 0 END) as commitments_pending,
            SUM(CASE WHEN commitment_status = ? AND commitment_date IS NOT NULL AND commitment_date >= ? AND commitment_date <= ? THEN 1 ELSE 0 END) as commitments_broken,
            COALESCE(SUM(CASE WHEN commitment_date IS NOT NULL AND commitment_date >= ? AND commitment_date <= ? THEN commitment_amount ELSE 0 END), 0) as commitment_amount_total
        ', [
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            $periodStart->toDateTimeString(),
            $periodEnd->toDateTimeString(),
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            'fulfilled',
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            'pending',
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            'broken',
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
        ])->first();

        $periodStatusAgg = (clone $followupsBase)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->selectRaw('
                COUNT(*) as total_followups,
                SUM(CASE WHEN overdue_installments > 0 THEN 1 ELSE 0 END) as overdue_followups,
                SUM(CASE WHEN management_status = ? THEN 1 ELSE 0 END) as resolved_followups,
                SUM(CASE WHEN management_status = ? THEN 1 ELSE 0 END) as in_progress_followups
            ', ['resolved', 'in_progress'])
            ->first();

        $logsQuery = DB::table('collection_followup_logs as l')
            ->leftJoin('collection_followups as f', 'f.followup_id', '=', 'l.followup_id')
            ->whereBetween('l.logged_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()]);
        if ($employeeId) {
            $logsQuery->whereRaw('COALESCE(l.employee_id, f.assigned_employee_id) = ?', [$employeeId]);
        }

        $logsAgg = $logsQuery->selectRaw('
            COUNT(*) as total_logs,
            SUM(CASE WHEN l.channel = ? THEN 1 ELSE 0 END) as calls_count,
            SUM(CASE WHEN l.channel = ? THEN 1 ELSE 0 END) as whatsapp_count,
            SUM(CASE WHEN l.channel = ? THEN 1 ELSE 0 END) as email_count,
            SUM(CASE WHEN l.result IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as contacted_count,
            SUM(CASE WHEN l.result IN (?, ?) THEN 1 ELSE 0 END) as unreachable_count
        ', ['call', 'whatsapp', 'email', 'contacted', 'resolved', 'sent', 'letter_sent', 'unreachable', 'not_reached'])->first();

        $recoveredQuery = DB::table('payment_schedules as ps')
            ->join('collection_followups as cf', 'cf.contract_id', '=', 'ps.contract_id')
            ->where('ps.status', 'pagado')
            ->whereBetween('ps.paid_date', [$periodStart->toDateString(), $periodEnd->toDateString()]);

        if ($employeeId) {
            $recoveredQuery->where('cf.assigned_employee_id', $employeeId);
        }

        $recoveredAmount = $recoveredQuery->sum('ps.amount') ?? 0;

        $leaderboard = $this->computeLeaderboard($periodStart, $periodEnd);

        $totalLogsFromLeaderboard = 0;
        $contactedFromLeaderboard = 0;
        $unreachableFromLeaderboard = 0;
        foreach ($leaderboard as $row) {
            $totalLogsFromLeaderboard += (int) ($row['period_logs'] ?? 0);
            $contactedFromLeaderboard += (int) ($row['period_contacted'] ?? 0);
            $unreachableFromLeaderboard += (int) ($row['period_unreachable'] ?? 0);
        }

        return [
            'period' => [
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
            ],

            'total_followups' => (int) ($portfolioAgg->total_followups ?? 0),
            'overdue_followups' => (int) ($portfolioAgg->overdue_followups ?? 0),
            'resolved_followups' => (int) ($portfolioAgg->resolved_followups ?? 0),
            'in_progress_followups' => (int) ($portfolioAgg->in_progress_followups ?? 0),
            'period_total_followups' => (int) ($periodStatusAgg->total_followups ?? 0),
            'period_overdue_followups' => (int) ($periodStatusAgg->overdue_followups ?? 0),
            'period_resolved_followups' => (int) ($periodStatusAgg->resolved_followups ?? 0),
            'period_in_progress_followups' => (int) ($periodStatusAgg->in_progress_followups ?? 0),
            'total_logs' => (int) $totalLogsFromLeaderboard,
            'calls_count' => (int) ($logsAgg->calls_count ?? 0),
            'whatsapp_count' => (int) ($logsAgg->whatsapp_count ?? 0),
            'email_count' => (int) ($logsAgg->email_count ?? 0),
            'contacted_count' => (int) $contactedFromLeaderboard,
            'unreachable_count' => (int) $unreachableFromLeaderboard,
            'commitments_total' => (int) ($periodFollowupsAgg->commitments_total ?? 0),
            'commitments_fulfilled' => (int) ($periodFollowupsAgg->commitments_fulfilled ?? 0),
            'commitments_pending' => (int) ($periodFollowupsAgg->commitments_pending ?? 0),
            'commitments_broken' => (int) ($periodFollowupsAgg->commitments_broken ?? 0),
            'commitment_amount_total' => round((float) ($periodFollowupsAgg->commitment_amount_total ?? 0), 2),
            'due_in_period' => (int) ($periodFollowupsAgg->due_in_period ?? 0),
            'overdue_in_period' => (int) ($periodFollowupsAgg->overdue_in_period ?? 0),
            'new_cases_in_period' => (int) ($periodFollowupsAgg->new_cases_in_period ?? 0),
            'total_amount' => round((float) ($portfolioAgg->total_amount ?? 0), 2),
            'recovered_amount' => round((float) $recoveredAmount, 2),

            'leaderboard' => $leaderboard,
        ];
    }

    private function computeLeaderboard(Carbon $periodStart, Carbon $periodEnd): array
    {
        $followupsAggRows = DB::table('collection_followups')
            ->selectRaw('
                assigned_employee_id as employee_id,
                COUNT(*) as portfolio_cases,
                SUM(CASE WHEN overdue_installments > 0 THEN 1 ELSE 0 END) as portfolio_overdue_cases,
                SUM(CASE WHEN management_status = ? THEN 1 ELSE 0 END) as portfolio_resolved_cases,
                SUM(CASE WHEN management_status = ? THEN 1 ELSE 0 END) as portfolio_in_progress_cases,
                COALESCE(SUM(pending_amount), 0) as portfolio_pending_amount,
                SUM(CASE WHEN commitment_date IS NOT NULL AND commitment_date >= ? AND commitment_date <= ? THEN 1 ELSE 0 END) as commitments_total,
                SUM(CASE WHEN commitment_status = ? AND commitment_date IS NOT NULL AND commitment_date >= ? AND commitment_date <= ? THEN 1 ELSE 0 END) as commitments_fulfilled
            ', [
                'resolved',
                'in_progress',
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
                'fulfilled',
                $periodStart->toDateString(),
                $periodEnd->toDateString(),
            ])
            ->groupBy('assigned_employee_id')
            ->get();

        $followupsByEmployeeKey = [];
        foreach ($followupsAggRows as $r) {
            $key = $r->employee_id === null ? 'null' : (string) (int) $r->employee_id;
            $followupsByEmployeeKey[$key] = $r;
        }

        $logsAggRows = DB::table('collection_followup_logs as l')
            ->leftJoin('collection_followups as f', 'f.followup_id', '=', 'l.followup_id')
            ->whereBetween('l.logged_at', [$periodStart->toDateTimeString(), $periodEnd->toDateTimeString()])
            ->selectRaw('
                COALESCE(l.employee_id, f.assigned_employee_id) as employee_id,
                COUNT(*) as total_logs,
                SUM(CASE WHEN l.result IN (?, ?, ?, ?) THEN 1 ELSE 0 END) as contacted_count,
                SUM(CASE WHEN l.result IN (?, ?) THEN 1 ELSE 0 END) as unreachable_count
            ', ['contacted', 'resolved', 'sent', 'letter_sent', 'unreachable', 'not_reached'])
            ->groupByRaw('COALESCE(l.employee_id, f.assigned_employee_id)')
            ->get();

        $logsByEmployeeKey = [];
        foreach ($logsAggRows as $r) {
            $key = $r->employee_id === null ? 'null' : (string) (int) $r->employee_id;
            $logsByEmployeeKey[$key] = $r;
        }

        $recoveredAggRows = DB::table('payment_schedules as ps')
            ->join('collection_followups as cf', 'cf.contract_id', '=', 'ps.contract_id')
            ->where('ps.status', 'pagado')
            ->whereBetween('ps.paid_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->selectRaw('
                cf.assigned_employee_id as employee_id,
                COALESCE(SUM(ps.amount), 0) as recovered_amount,
                COUNT(*) as recovered_installments
            ')
            ->groupBy('cf.assigned_employee_id')
            ->get();

        $recoveredByEmployeeKey = [];
        foreach ($recoveredAggRows as $r) {
            $key = $r->employee_id === null ? 'null' : (string) (int) $r->employee_id;
            $recoveredByEmployeeKey[$key] = $r;
        }

        $allEmployeeKeys = [];
        foreach (array_keys($followupsByEmployeeKey) as $k) {
            $allEmployeeKeys[$k] = true;
        }
        foreach (array_keys($logsByEmployeeKey) as $k) {
            $allEmployeeKeys[$k] = true;
        }
        foreach (array_keys($recoveredByEmployeeKey) as $k) {
            $allEmployeeKeys[$k] = true;
        }

        $employeeIds = [];
        foreach (array_keys($allEmployeeKeys) as $k) {
            if ($k === 'null') {
                continue;
            }
            $employeeIds[] = (int) $k;
        }

        $employees = empty($employeeIds)
            ? collect()
            : DB::table('employees as e')
                ->leftJoin('users as u', 'u.user_id', '=', 'e.user_id')
                ->whereIn('e.employee_id', $employeeIds)
                ->select(['e.employee_id', 'e.employee_code', 'u.first_name', 'u.last_name'])
                ->get()
                ->keyBy('employee_id');

        $rows = [];

        foreach (array_keys($allEmployeeKeys) as $employeeKey) {
            $employeeId = $employeeKey === 'null' ? null : (int) $employeeKey;
            $employee = $employeeId ? ($employees[$employeeId] ?? null) : null;
            $followups = $followupsByEmployeeKey[$employeeKey] ?? null;

            $employeeName = 'Sin asignar';
            $employeeCode = null;
            if ($employee) {
                $employeeName = trim(($employee->first_name ?? '') . ' ' . ($employee->last_name ?? ''));
                $employeeName = $employeeName !== '' ? $employeeName : 'Sin nombre';
                $employeeCode = $employee->employee_code ?? null;
            }

            $logs = $logsByEmployeeKey[$employeeKey] ?? null;
            $recovered = $recoveredByEmployeeKey[$employeeKey] ?? null;

            $contacted = (int) ($logs->contacted_count ?? 0);
            $unreachable = (int) ($logs->unreachable_count ?? 0);
            $contactDen = $contacted + $unreachable;
            $contactRate = $contactDen > 0 ? round(($contacted / $contactDen) * 100, 1) : 0.0;

            $commitmentsTotal = (int) ($followups->commitments_total ?? 0);
            $commitmentsFulfilled = (int) ($followups->commitments_fulfilled ?? 0);
            $fulfillmentRate = $commitmentsTotal > 0 ? round(($commitmentsFulfilled / $commitmentsTotal) * 100, 1) : 0.0;

            $pendingAmount = (float) ($followups->portfolio_pending_amount ?? 0);
            $recoveredAmount = (float) ($recovered->recovered_amount ?? 0);
            $recoveryRate = $pendingAmount > 0 ? round(($recoveredAmount / $pendingAmount) * 100, 1) : 0.0;

            $rows[] = [
                'employee_id' => $employeeId,
                'employee_code' => $employeeCode,
                'employee_name' => $employeeName,
                'portfolio_cases' => (int) ($followups->portfolio_cases ?? 0),
                'portfolio_overdue_cases' => (int) ($followups->portfolio_overdue_cases ?? 0),
                'portfolio_in_progress_cases' => (int) ($followups->portfolio_in_progress_cases ?? 0),
                'portfolio_resolved_cases' => (int) ($followups->portfolio_resolved_cases ?? 0),
                'portfolio_pending_amount' => round($pendingAmount, 2),
                'period_logs' => (int) ($logs->total_logs ?? 0),
                'period_contacted' => $contacted,
                'period_unreachable' => $unreachable,
                'period_contact_rate' => $contactRate,
                'period_commitments_total' => $commitmentsTotal,
                'period_commitments_fulfilled' => $commitmentsFulfilled,
                'period_fulfillment_rate' => $fulfillmentRate,
                'period_recovered_amount' => round($recoveredAmount, 2),
                'period_recovered_installments' => (int) ($recovered->recovered_installments ?? 0),
                'period_recovery_rate' => $recoveryRate,
            ];
        }

        usort($rows, fn ($a, $b) => ($b['period_recovered_amount'] <=> $a['period_recovered_amount']));
        return $rows;
    }
}
