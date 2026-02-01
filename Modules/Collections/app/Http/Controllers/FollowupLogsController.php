<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Collections\app\Models\FollowupLog;
use Modules\Collections\app\Models\Followup;

class FollowupLogsController extends Controller
{
    public function index(Request $request)
    {
        $query = FollowupLog::query();
        if ($request->filled('followup_id')) {
            $query->where('followup_id', $request->integer('followup_id'));
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->integer('client_id'));
        }
        $logs = $query->orderByDesc('logged_at')->orderByDesc('log_id')->limit(100)->get();
        return response()->json(['success' => true, 'data' => $logs]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'followup_id' => 'nullable|integer',
            'client_id' => 'required|integer',
            'employee_id' => 'nullable|integer',
            'channel' => 'required|string',
            'result' => 'nullable|string',
            'notes' => 'nullable|string',
            'logged_at' => 'nullable|date',
        ]);

        if (empty($data['logged_at'])) {
            $data['logged_at'] = now();
        }

        $log = FollowupLog::create($data);

        // Actualizar resumen en seguimiento
        if (!empty($data['followup_id'])) {
            $followup = Followup::find($data['followup_id']);
            if ($followup) {
                $followup->contact_date = $data['logged_at'];
                $followup->action_taken = $data['channel'];
                if (!empty($data['result'])) {
                    $followup->management_result = $data['result'];
                    $res = strtolower($data['result']);
                    if ($res === 'resolved' || $res === 'fulfilled') {
                        $followup->management_status = 'resolved';
                    } elseif ($res === 'unreachable') {
                        $followup->management_status = 'unreachable';
                    }
                }
                if (!$followup->management_status || $followup->management_status === 'pending') {
                    $followup->management_status = 'in_progress';
                }
                // Append notas
                $prefix = sprintf('[%s %s]', now()->format('Y-m-d H:i'), strtoupper($data['channel']));
                $newNotes = trim(($followup->management_notes ? ($followup->management_notes . "\n") : '') . $prefix . ' ' . ($data['notes'] ?? ''));
                $followup->management_notes = $newNotes;
                $followup->save();
            }
        }

        return response()->json(['success' => true, 'data' => $log], 201);
    }
}
