<?php

namespace Modules\ServiceDesk\Http\Controllers;

use Illuminate\Http\Request;
use Modules\ServiceDesk\Http\Requests\ServiceRequestRequest;
use Modules\ServiceDesk\Models\ServiceRequest;
use Modules\ServiceDesk\Repositories\ServiceRequestRepository;
use Modules\ServiceDesk\Transformers\ServiceRequestResource;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Modules\ServiceDesk\Events\TicketUpdated;

class ServiceRequestController extends Controller
{
    protected $repo;
    
    public function __construct(ServiceRequestRepository $repo)
    {
        $this->repo = $repo;
        $this->middleware('auth:sanctum');
        // Authorization handled by ServiceRequestPolicy (includes before() for admin bypass)
        // No middleware 'can:' needed - Policy handles everything
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

        // Calculate SLA based on priority
        $slaConfig = \Modules\ServiceDesk\Models\SlaConfig::where('priority', $data['priority'])->first();
        if ($slaConfig) {
            $data['sla_due_at'] = now()->addHours($slaConfig->resolution_hours);
        }

        $ticket = $this->repo->create($data);
        
        // Broadcast ticket created event for real-time updates
        event(new TicketUpdated($ticket, 'created'));

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

        // Broadcast ticket updated event for real-time updates
        event(new TicketUpdated($updated, 'updated'));

        return new ServiceRequestResource($updated);
    }

    public function destroy($ticket_id)
    {
        $ticket = $this->repo->find($ticket_id);
        $this->authorize('delete', $ticket);

        // Broadcast before delete for real-time updates
        event(new TicketUpdated($ticket, 'deleted'));

        $this->repo->delete($ticket_id);

        return response()->json(['message' => 'Ticket eliminado'], 204);
    }

    /**
     * Assign ticket to a technician
     * POST /requests/{ticket_id}/assign
     */
    public function assign(Request $request, $ticket_id)
    {
        Log::info("Intento de asignación de ticket", [
            'ticket_id' => $ticket_id, 
            'incoming_user_id' => $request->user_id,
            'user_exists' => \Modules\Security\Models\User::find($request->user_id) ? 'YES' : 'NO'
        ]);

        $request->validate([
            'user_id' => 'required|exists:users,user_id',
        ]);

        $ticket = $this->repo->find($ticket_id);
        $this->authorize('update', $ticket);

        $updated = $this->repo->assignTicket($ticket_id, $request->user_id);

        // Broadcast ticket assigned event
        event(new TicketUpdated($updated, 'assigned'));

        return new ServiceRequestResource($updated);
    }

    /**
     * Change ticket status
     * POST /requests/{ticket_id}/status
     */
    public function changeStatus(Request $request, $ticket_id)
    {
        $request->validate([
            'status' => 'required|in:abierto,en_proceso,cerrado',
            'notes' => 'nullable|string|max:500',
        ]);

        $ticket = $this->repo->find($ticket_id);
        $this->authorize('update', $ticket);

        $updated = $this->repo->changeStatus($ticket_id, $request->status, $request->notes);

        // Broadcast status change event
        event(new TicketUpdated($updated, 'updated'));

        return new ServiceRequestResource($updated);
    }

    /**
     * Escalate a ticket
     * POST /requests/{ticket_id}/escalate
     */
    public function escalate(Request $request, $ticket_id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $ticket = $this->repo->find($ticket_id);
        $this->authorize('update', $ticket);

        $updated = $this->repo->escalate($ticket_id, $request->reason);

        // Broadcast escalation event
        event(new TicketUpdated($updated, 'updated'));

        return new ServiceRequestResource($updated);
    }

    /**
     * Add a comment/action to a ticket
     * POST /requests/{ticket_id}/comment
     */
    public function addComment(Request $request, $ticket_id)
    {
        $request->validate([
            'notes' => 'required|string|max:2000',
            'action_type' => 'nullable|string|max:50',
        ]);

        $ticket = $this->repo->find($ticket_id);
        $this->authorize('update', $ticket);

        $actionType = $request->input('action_type', 'comment');
        $updated = $this->repo->addComment($ticket_id, $request->notes, $actionType);

        // Broadcast comment added event
        event(new TicketUpdated($updated, 'updated'));

        return new ServiceRequestResource($updated);
    }

    /**
     * Get ticket actions/history
     * GET /requests/{ticket_id}/actions
     */
    public function getActions($ticket_id)
    {
        $ticket = $this->repo->findWithRelations($ticket_id);
        $this->authorize('view', $ticket);

        return response()->json([
            'data' => $ticket->actions->map(function($action) {
                return [
                    'action_id' => $action->action_id,
                    'action_type' => $action->action_type,
                    'notes' => $action->notes,
                    'performed_at' => $action->performed_at,
                    'user' => $action->user ? [
                        'user_id' => $action->user->user_id,
                        'first_name' => $action->user->first_name,
                        'last_name' => $action->user->last_name,
                    ] : null,
                ];
            })
        ]);
    }
}
