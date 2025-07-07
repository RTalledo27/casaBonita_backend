<?php

namespace Modules\ServiceDesk\Repositories;

use Illuminate\Support\Facades\Log;
use Modules\Security\Models\User;
use Modules\ServiceDesk\Models\ServiceRequest;

class ServiceDeskDashRepository
{
    public function getDashboardData($params = [])
    {


        Log::info('DASH_PARAMS:', $params);


        // 1. Definir rango actual
        if (!empty($params['start']) && !empty($params['end'])) {
            $dateFrom = \Carbon\Carbon::parse($params['start'])->startOfDay();
            $dateTo = \Carbon\Carbon::parse($params['end'])->endOfDay();

            // periodo anterior: mismo rango, justo antes
            $days = $dateFrom->diffInDays($dateTo) + 1;
            $lastFrom = (clone $dateFrom)->subDays($days);
            $lastTo = (clone $dateFrom)->subDay();
        } else {
            $period = $params['period'] ?? 'month';
            $dateFrom = now()->startOfMonth();
            $dateTo = now();

            if ($period === 'week') {
                $dateFrom = now()->startOfWeek();
            }
            if ($period === 'today') {
                $dateFrom = now()->startOfDay();
            }

            // periodo anterior según periodo
            if ($period === 'month') {
                $lastFrom = now()->subMonth()->startOfMonth();
                $lastTo = now()->subMonth()->endOfMonth();
            } elseif ($period === 'week') {
                $lastFrom = now()->subWeek()->startOfWeek();
                $lastTo = now()->subWeek()->endOfWeek();
            } elseif ($period === 'today') {
                $lastFrom = now()->subDay()->startOfDay();
                $lastTo = now()->subDay()->endOfDay();
            } else {
                $lastFrom = null;
                $lastTo = null;
            }
        }

        // KPIs actuales
        $totalTicketsNow = ServiceRequest::whereBetween('opened_at', [$dateFrom, $dateTo])->count();
        $openTicketsNow = ServiceRequest::where('status', 'abierto')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->count();
        $closedTicketsNow = ServiceRequest::where('status', 'cerrado')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->count();

        // SLA cumplido (cerrados y en fecha)
        $slaRateNow = ServiceRequest::where('status', 'cerrado')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->whereNotNull('sla_due_at')
            ->whereColumn('updated_at', '<=', 'sla_due_at')
            ->count();

        $slaPercentNow = $closedTicketsNow > 0
            ? round(($slaRateNow / $closedTicketsNow) * 100, 1)
            : 0;

        // Tiempo promedio de resolución (minutos a horas)
        $avgTimeNow = ServiceRequest::where('status', 'cerrado')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->whereNotNull('opened_at')
            ->whereNotNull('updated_at')
            ->get()
            ->map(fn($t) => $t->opened_at->diffInMinutes($t->updated_at))
            ->avg() ?? 0;

        // KPIs periodo anterior (solo si hay fechas)
        $openTicketsLast = $lastFrom && $lastTo ? ServiceRequest::where('status', 'abierto')
            ->whereBetween('opened_at', [$lastFrom, $lastTo])
            ->count() : 0;
        $totalTicketsLast = $lastFrom && $lastTo ? ServiceRequest::whereBetween('opened_at', [$lastFrom, $lastTo])->count() : 0;
        $closedTicketsLast = $lastFrom && $lastTo ? ServiceRequest::where('status', 'cerrado')->whereBetween('opened_at', [$lastFrom, $lastTo])->count() : 0;
        $slaRateLast = $lastFrom && $lastTo ? ServiceRequest::where('status', 'cerrado')->whereBetween('opened_at', [$lastFrom, $lastTo])
            ->whereNotNull('sla_due_at')->whereColumn('updated_at', '<=', 'sla_due_at')->count() : 0;
        $slaPercentLast = $closedTicketsLast > 0 ? round(($slaRateLast / $closedTicketsLast) * 100, 1) : 0;

        $avgTimeLast = $lastFrom && $lastTo ? ServiceRequest::where('status', 'cerrado')
            ->whereBetween('opened_at', [$lastFrom, $lastTo])
            ->whereNotNull('opened_at')
            ->whereNotNull('updated_at')
            ->get()
            ->map(fn($t) => $t->opened_at->diffInMinutes($t->updated_at))
            ->avg() ?? 0 : 0;

        // Variaciones
        $variationOpen = $openTicketsLast > 0
            ? round((($openTicketsNow - $openTicketsLast) / $openTicketsLast) * 100, 1)
            : 0;
        $variationTotal = $totalTicketsLast > 0
            ? round((($totalTicketsNow - $totalTicketsLast) / $totalTicketsLast) * 100, 1)
            : 0;
        $variationSLA = $slaPercentLast > 0
            ? round($slaPercentNow - $slaPercentLast, 1)
            : 0;
        $variationAvg = $avgTimeLast > 0
            ? round((($avgTimeNow - $avgTimeLast) / $avgTimeLast) * 100, 1)
            : 0;

        // Tickets por estado y prioridad (charts)
        $statusCounts = ServiceRequest::selectRaw('status, COUNT(*) as total')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->groupBy('status')
            ->pluck('total', 'status')->toArray();
        $priorityCounts = ServiceRequest::selectRaw('priority, COUNT(*) as total')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->groupBy('priority')
            ->pluck('total', 'priority')->toArray();

        $statusChartData = [
            'labels' => ['Abierto', 'En Proceso', 'Cerrado'],
            'datasets' => [[
                'data' => [
                    $statusCounts['abierto'] ?? 0,
                    $statusCounts['en_proceso'] ?? 0,
                    $statusCounts['cerrado'] ?? 0
                ],
                'backgroundColor' => ['#2563eb', '#eab308', '#22c55e'],
            ]]
        ];
        $priorityChartData = [
            'labels' => ['Baja', 'Media', 'Alta', 'Crítica'],
            'datasets' => [[
                'data' => [
                    $priorityCounts['baja'] ?? 0,
                    $priorityCounts['media'] ?? 0,
                    $priorityCounts['alta'] ?? 0,
                    $priorityCounts['critica'] ?? 0,
                ],
                'backgroundColor' => ['#06b6d4', '#64748b', '#eab308', '#ef4444']
            ]]
        ];

        // Tickets recientes (solo en el rango actual)
        $recentTickets = ServiceRequest::with('creator')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->orderByDesc('opened_at')
            ->limit(5)
            ->get();

        // Alertas (también filtra por rango actual)
        $slaInDanger = ServiceRequest::where('status', 'abierto')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<=', now()->addHours(4))
            ->count();
        $ticketsToEscalate = ServiceRequest::where('status', 'en_proceso')
            ->whereBetween('opened_at', [$dateFrom, $dateTo])
            ->where('priority', 'critica')
            ->whereNull('assigned_to')
            ->count();
        $ticketsWithoutAssignee = ServiceRequest::whereBetween('opened_at', [$dateFrom, $dateTo])
            ->whereNull('assigned_to')->count();

        $alerts = [];
        if ($slaInDanger > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'alert-triangle',
                'message' => "SLA en riesgo: {$slaInDanger} tickets próximos a vencer"
            ];
        }
        if ($ticketsToEscalate > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'clock',
                'message' => "Escalación: {$ticketsToEscalate} tickets críticos requieren atención"
            ];
        }
        if ($ticketsWithoutAssignee > 0) {
            $alerts[] = [
                'type' => 'info',
                'icon' => 'info',
                'message' => "{$ticketsWithoutAssignee} tickets sin asignar"
            ];
        }

        // Técnicos top
        $topTechs = User::withCount(['assignedTickets' => function ($q) use ($dateFrom, $dateTo) {
            $q->where('status', 'cerrado')->whereBetween('updated_at', [$dateFrom, $dateTo]);
        }])
            ->orderByDesc('assigned_tickets_count')
            ->limit(3)
            ->get();


        Log::info('Status Counts:', $statusCounts);
        Log::info('Priority Counts:', $priorityCounts);


        // Return final
        return [
            'total_tickets'      => $totalTicketsNow,
            'variation_tickets'  => ($variationTotal > 0 ? '+' : '') . $variationTotal . '% vs periodo anterior',
            'open_tickets'       => $openTicketsNow,
            'open_tickets_note'  => ($variationOpen > 0 ? '+' : '') . $variationOpen . '% vs periodo anterior',
            'sla_rate'           => $slaPercentNow . '%',
            'variation_sla'      => ($variationSLA > 0 ? '+' : '') . $variationSLA . '% vs periodo anterior',
            'avg_time'           => round($avgTimeNow / 60, 2) . 'h',
            'avg_time_note'      => ($variationAvg > 0 ? '+' : '') . $variationAvg . '% vs periodo anterior',
            'recent_incidents'   => $recentTickets,
            'alerts'             => $alerts,
            'top_techs'          => $topTechs,
            'statusChartData'    => $statusChartData,
            'priorityChartData'  => $priorityChartData,
        ];
    }
}
