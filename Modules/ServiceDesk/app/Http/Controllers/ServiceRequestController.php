<?php

namespace Modules\ServiceDesk\Http\Controllers;

use Illuminate\Http\Request;
use Modules\ServiceDesk\Http\Requests\ServiceRequestRequest;
use Modules\ServiceDesk\Models\ServiceRequest;
use Modules\ServiceDesk\Repositories\ServiceRequestRepository;
use Modules\ServiceDesk\Transformers\ServiceRequestResource;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

class ServiceRequestController extends Controller
{
    protected $repo;
    public function __construct(ServiceRequestRepository $repo)
    {
        $this->repo = $repo;
        $this->middleware('auth:sanctum');
        $this->middleware('can:service-desk.tickets.view')->only(['index', 'show']);
        $this->middleware('can:service-desk.tickets.store')->only('store');
        $this->middleware('can:service-desk.tickets.update')->only('update');
        $this->middleware('can:service-desk.tickets.delete')->only('destroy');
        $this->authorizeResource(ServiceRequest::class, 'request'); // <--- ESTA LÍNEA BIEN!
    }
    

    public function index(Request $request)
    {
        $tickets = $this->repo->listWithFilters($request->all());
        return ServiceRequestResource::collection($tickets);
    }

    public function store(ServiceRequestRequest $request)
    {
        $data = $request->validated();
        $data['opened_by'] = auth()->user()->user_id;
        $data['opened_at'] = now();

        $ticket = $this->repo->create($data);
        

        // Notificar/emitir evento aquí si quieres

        return new ServiceRequestResource($ticket->load(['creator', 'actions.user']));
    }

    public function show(ServiceRequest $request)
    {
        // Si quieres cargar relaciones adicionales:
        $request->load(['creator', 'actions.user', 'contract']);
    
        // Log (opcional, para depuración)
        Log::info('Ticket cargado', [
            'ticket_id' => $request->ticket_id,
            'opened_by' => $request->opened_by,
            'user_id'   => auth()->id(),
            'roles'     => auth()->user()?->roles?->pluck('name'),
        ]);
    
        return new ServiceRequestResource($request);
    }
    

    public function update(ServiceRequestRequest $request, $ticket_id)
    {
        $ticket = $this->repo->find($ticket_id);
        $this->authorize('update', $ticket);

        $updated = $this->repo->update($ticket_id, $request->validated());

        return new ServiceRequestResource($updated);
    }

    public function destroy($ticket_id)
    {
        $ticket = $this->repo->find($ticket_id);
        $this->authorize('delete', $ticket);

        $this->repo->delete($ticket_id);

        return response()->json(['message' => 'Ticket eliminado'], 204);
    }
}
