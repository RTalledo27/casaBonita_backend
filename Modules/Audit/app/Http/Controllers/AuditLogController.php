<?php

namespace Modules\Audit\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Audit\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        return AuditLog::with('user')->paginate(15);
    }

    public function show(AuditLog $auditLog)
    {
        return $auditLog->load('user');
    }
}
