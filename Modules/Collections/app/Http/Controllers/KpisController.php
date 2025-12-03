<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Collections\app\Models\Followup;
use Modules\Collections\app\Models\FollowupLog;

class KpisController extends Controller
{
    public function index()
    {
        $totalFollowups = Followup::count();
        $overdue = Followup::where('overdue_installments', '>', 0)->count();
        $resolved = Followup::where('management_status', 'resolved')->count();
        $inProgress = Followup::where('management_status', 'in_progress')->count();
        $logs = FollowupLog::count();

        return response()->json(['success' => true, 'data' => [
            'total_followups' => $totalFollowups,
            'overdue_followups' => $overdue,
            'resolved_followups' => $resolved,
            'in_progress_followups' => $inProgress,
            'total_logs' => $logs,
        ]]);
    }
}

