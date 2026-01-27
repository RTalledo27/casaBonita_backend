<?php

namespace Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Modules\Security\Transformers\UnifiedActivityLogResource;

class ActivityLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:security.audit.view');
    }

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min(100, $perPage));

        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['created_at', 'action', 'user_id', 'ip_address'];
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'created_at';
        }

        $includeSystem = filter_var($request->get('include_system', true), FILTER_VALIDATE_BOOL)
            && Schema::hasTable('system_activity_logs');

        $userLogs = DB::table('user_activity_logs')
            ->select([
                'id',
                'user_id',
                DB::raw('NULL as actor_identifier'),
                'action',
                'details',
                'ip_address',
                'user_agent',
                'metadata',
                'created_at',
                DB::raw("'user' as source"),
            ]);

        $base = $userLogs;

        if ($includeSystem) {
            $systemLogs = DB::table('system_activity_logs')
                ->select([
                    'id',
                    'user_id',
                    'actor_identifier',
                    'action',
                    'details',
                    'ip_address',
                    'user_agent',
                    'metadata',
                    'created_at',
                    DB::raw("'system' as source"),
                ]);
            $base = $base->unionAll($systemLogs);
        }

        $query = DB::query()
            ->fromSub($base, 'logs')
            ->leftJoin('users as u', 'u.user_id', '=', 'logs.user_id')
            ->select([
                'logs.*',
                'u.user_id as user_user_id',
                'u.username as user_username',
                'u.first_name as user_first_name',
                'u.last_name as user_last_name',
                'u.email as user_email',
            ])
            ->when($request->get('user_id'), fn ($q, $v) => $q->where('logs.user_id', (int) $v))
            ->when($request->get('action'), fn ($q, $v) => $q->where('logs.action', (string) $v))
            ->when($request->get('ip_address'), fn ($q, $v) => $q->where('logs.ip_address', 'like', '%' . $v . '%'))
            ->when($request->get('date_from'), fn ($q, $v) => $q->where('logs.created_at', '>=', $v))
            ->when($request->get('date_to'), fn ($q, $v) => $q->where('logs.created_at', '<=', $v))
            ->when($request->get('search'), function ($q, $search) {
                $search = trim((string) $search);
                if ($search === '') return;
                $q->where(function ($qq) use ($search) {
                    $qq->where('logs.action', 'like', "%{$search}%")
                        ->orWhere('logs.details', 'like', "%{$search}%")
                        ->orWhere('logs.ip_address', 'like', "%{$search}%")
                        ->orWhere('logs.actor_identifier', 'like', "%{$search}%")
                        ->orWhere('u.username', 'like', "%{$search}%")
                        ->orWhere('u.email', 'like', "%{$search}%")
                        ->orWhere('u.first_name', 'like', "%{$search}%")
                        ->orWhere('u.last_name', 'like', "%{$search}%");
                });
            })
            ->orderBy("logs.{$sortBy}", $sortDir);

        return UnifiedActivityLogResource::collection(
            $query->paginate($perPage)
        );
    }
}
