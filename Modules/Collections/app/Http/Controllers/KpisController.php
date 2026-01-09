<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Modules\Collections\app\Models\Followup;
use Modules\Collections\app\Models\FollowupLog;
use Modules\Sales\Models\PaymentSchedule;

class KpisController extends Controller
{
    public function index()
    {
        // Estado de seguimientos
        $totalFollowups = Followup::count();
        $overdue = Followup::where('overdue_installments', '>', 0)->count();
        $resolved = Followup::where('management_status', 'resolved')->count();
        $inProgress = Followup::where('management_status', 'in_progress')->count();
        
        // Actividad de gestión
        $totalLogs = FollowupLog::count();
        $callsCount = FollowupLog::where('channel', 'call')->count();
        $whatsappCount = FollowupLog::where('channel', 'whatsapp')->count();
        $emailCount = FollowupLog::where('channel', 'email')->count();
        
        // Efectividad de contacto (basado en el resultado)
        $contactedCount = FollowupLog::where('result', 'contacted')->count();
        $unreachableCount = FollowupLog::where('result', 'not_reached')->count();
        
        // Compromisos de pago
        $commitmentsTotal = Followup::whereNotNull('commitment_date')->count();
        $commitmentsFulfilled = Followup::where('commitment_status', 'fulfilled')->count();
        $commitmentsPending = Followup::where('commitment_status', 'pending')
            ->where('commitment_date', '>=', Carbon::today())
            ->count();
        
        // Métricas financieras
        $totalAmount = Followup::sum('pending_amount') ?? 0;
        
        // Monto recuperado en el mes actual (pagos realizados en cuotas de seguimientos activos)
        $recoveredAmount = DB::table('payment_schedules as ps')
            ->join('collection_followups as cf', 'cf.contract_id', '=', 'ps.contract_id')
            ->where('ps.status', 'pagado')
            ->whereMonth('ps.paid_date', Carbon::now()->month)
            ->whereYear('ps.paid_date', Carbon::now()->year)
            ->sum('ps.amount') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
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
                
                // Efectividad de contacto
                'contacted_count' => $contactedCount,
                'unreachable_count' => $unreachableCount,
                
                // Compromisos
                'commitments_total' => $commitmentsTotal,
                'commitments_fulfilled' => $commitmentsFulfilled,
                'commitments_pending' => $commitmentsPending,
                
                // Financiero
                'total_amount' => round($totalAmount, 2),
                'recovered_amount' => round($recoveredAmount, 2),
            ]
        ]);
    }
}

