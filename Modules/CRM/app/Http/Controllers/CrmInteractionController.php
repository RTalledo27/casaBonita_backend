<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Http\Requests\{
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
        $this->authorizeResource(CrmInteraction::class, 'crm_interaction');
    }

    public function index(Request $request)
    {
        $data = $request->only(['client_id', 'date_from', 'date_to', 'channel', 'per_page']);
        return CrmInteractionResource::collection($this->repo->paginate($data));
    }

    public function store(StoreCrmInteractionRequest $req)
    {
        return new CrmInteractionResource($this->repo->create($req->validated()));
    }

    public function show(CrmInteraction $crm_interaction)
    {
        return new CrmInteractionResource($crm_interaction->load(['client', 'user']));
    }

    public function update(UpdateCrmInteractionRequest $req, CrmInteraction $crm_interaction)
    {
        return new CrmInteractionResource($this->repo->update($crm_interaction, $req->validated()));
    }

    public function destroy(CrmInteraction $crm_interaction)
    {
        $this->repo->delete($crm_interaction);
        return response()->noContent();
    }
}
