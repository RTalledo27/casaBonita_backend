<?php

namespace Modules\ServiceDesk\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ServiceDesk\Models\ServiceAction;

class ServiceActionController extends Controller
{
    public function index()
    {
        return ServiceAction::with('request', 'user')->paginate(15);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'ticket_id'        => 'required|exists:service_requests,ticket_id',
            'user_id'          => 'nullable|exists:users,user_id',
            'performed_at'     => 'required|date',
            'notes'            => 'nullable|string',
            'next_action_date' => 'nullable|date',
        ]);
        return ServiceAction::create($data);
    }

    public function show(ServiceAction $serviceAction)
    {
        return $serviceAction->load('request', 'user');
    }

    public function destroy(ServiceAction $sa)
    {
        $sa->delete();
        return response()->noContent();
    }
}
