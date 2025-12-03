<?php

namespace Modules\Collections\app\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Collections\app\Models\FollowupLog;

class FollowupLogsController extends Controller
{
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

        $log = FollowupLog::create($data);
        return response()->json(['success' => true, 'data' => $log], 201);
    }
}

