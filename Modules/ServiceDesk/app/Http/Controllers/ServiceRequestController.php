<?php

namespace Modules\ServiceDesk\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\ServiceDesk\Models\ServiceRequest;

class ServiceRequestController extends Controller
{
    public function index()
    {
        return ServiceRequest::with('contract', 'actions')->paginate(15);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'contract_id'      => 'required|exists:contracts,contract_id',
            'opened_at'        => 'required|date',
            'ticket_type'      => 'required|in:garantia,mantenimiento,otro',
            'priority'         => 'required|in:baja,media,alta,critica',
            'status'           => 'required|in:abierto,en_proceso,cerrado',
            'description'      => 'nullable|string',
        ]);
        return ServiceRequest::create($data);
    }

    public function show(ServiceRequest $serviceRequest)
    {
        return $serviceRequest->load('contract', 'actions');
    }

    public function update(Request $r, ServiceRequest $sr)
    {
        $sr->update($r->all());
        return $sr->load('contract', 'actions');
    }

    public function destroy(ServiceRequest $sr)
    {
        $sr->delete();
        return response()->noContent();
    }
}
