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

class CrmInteractionController extends Controller
{
    public function __construct(private CrmInteractionRepository $repo)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:crm.access');
        $this->authorizeResource(CrmInteraction::class, 'interaction');
    }

    /** @group CRM - Interacciones
     * @urlParam client_id int required El ID del cliente relacionado
     */
    public function index(Request $request)
    {
        return CrmInteractionResource::collection($this->repo->paginate($request->all()));
    }

    public function store(CrmInteractionRequest $request)
    {
        return new CrmInteractionResource($this->repo->create($request->validated()));
    }

    public function show(CrmInteraction $interaction)
    {
        return new CrmInteractionResource($interaction->load('client'));
    }

    public function update(CrmInteractionRequest $request, CrmInteraction $interaction)
    {
        return new CrmInteractionResource($this->repo->update($interaction, $request->validated()));
    }

    public function destroy(CrmInteraction $interaction)
    {
        $this->repo->delete($interaction);
        return response()->noContent();
    }

}
