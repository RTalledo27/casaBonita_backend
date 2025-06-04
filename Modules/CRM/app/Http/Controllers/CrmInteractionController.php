<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Http\Requests\{
    CrmInteractionRequest,
    StoreCrmInteractionRequest,
    UpdateCrmInteractionRequest
};
use Modules\CRM\Models\CrmInteraction;
use Modules\CRM\Repositories\CrmInteractionRepository;
use Modules\CRM\Transformers\CrmInteractionResource;
use Modules\services\PusherNotifier;

class CrmInteractionController extends Controller
{
    public function __construct(
        private CrmInteractionRepository $interactions,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('can:crm.access');
        $this->middleware('permission:crm.interactions.view')->only(['index', 'show']);
        $this->middleware('permission:crm.interactions.store')->only('store');
        $this->middleware('permission:crm.interactions.update')->only('update');
        $this->middleware('permission:crm.interactions.delete')->only('destroy');
    }

    /**
     * Listar todas las interacciones (con filtros si es necesario).
     */
    public function index(Request $request)
    {
        $filters = $request->only(['client_id', 'user_id', 'channel', 'date']);
        return CrmInteractionResource::collection($this->interactions->paginate($filters));
    }

    /**
     * Crear una nueva interacción.
     */
    public function store(StoreCrmInteractionRequest $request)
    {
        $interaction = $this->interactions->create($request->validated());
        $this->pusher->notify('interaction', 'created', ['interaction' => new CrmInteractionResource($interaction)]);
        return new CrmInteractionResource($interaction);
    }

    /**
     * Mostrar una interacción específica.
     */
    public function show(CrmInteraction $interaction)
    {
        return new CrmInteractionResource($interaction);
    }

    /**
     * Actualizar una interacción existente.
     */
    public function update(UpdateCrmInteractionRequest $request, CrmInteraction $interaction)
    {
        $updated = $this->interactions->update($interaction, $request->validated());
        $this->pusher->notify('interaction', 'updated', ['interaction' => new CrmInteractionResource($updated)]);
        return new CrmInteractionResource($updated);
    }

    /**
     * Eliminar una interacción.
     */
    public function destroy(CrmInteraction $interaction)
    {
        $id = $interaction->interaction_id;
        $this->interactions->delete($interaction);
        $this->pusher->notify('interaction', 'deleted', ['interaction' => ['interaction_id' => $id]]);
        return response()->json(['message' => 'Interacción eliminada', 'interaction_id' => $id]);
    }
}
