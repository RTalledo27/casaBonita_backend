<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Integrations\Models\IntegrationLog;

class IntegrationLogController extends Controller
{
    public function index()
    {
        return IntegrationLog::paginate(15);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'service'   => 'required|string|max:60',
            'entity'    => 'required|string|max:60',
            'entity_id' => 'required|integer',
            'status'    => 'required|in:success,error',
            'message'   => 'nullable|string|max:255',
        ]);
        return IntegrationLog::create($data);
    }

    public function show(IntegrationLog $integrationLog)
    {
        return $integrationLog;
    }

    public function destroy(IntegrationLog $integrationLog)
    {
        $integrationLog->delete();
        return response()->noContent();
    }
}
