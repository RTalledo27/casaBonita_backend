<?php

namespace Modules\ServiceDesk\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ServiceDesk\Http\Requests\ServiceActionRequest;
use Modules\ServiceDesk\Models\ServiceAction;
use Modules\ServiceDesk\Repositories\ServiceActionRepository;
use Modules\ServiceDesk\Repositories\ServiceRequestRepository;
use Modules\ServiceDesk\Transformers\ServiceActionResource;

class ServiceActionController extends Controller
{

    protected $actionRepo;
    protected $requestRepo;

    public function __construct(
        ServiceActionRepository $actionRepo,
        ServiceRequestRepository $requestRepo
    ) {
        $this->actionRepo = $actionRepo;
        $this->requestRepo = $requestRepo;
    }

    public function index($ticket_id)
    {
        $ticket = $this->requestRepo->find($ticket_id);
        $this->authorize('view', $ticket);

        $actions = $this->actionRepo->listByTicket($ticket_id);
        return ServiceActionResource::collection($actions);
    }

    public function store(ServiceActionRequest $request, $ticket_id)
    {
        $ticket = $this->requestRepo->find($ticket_id);
        $this->authorize('addAction', $ticket);

        $data = $request->validated();
        $data['user_id'] = auth()->user()->user_id;
        $data['ticket_id'] = $ticket_id;
        $data['performed_at'] = now();

        $action = $this->actionRepo->create($data);

        // Si action_type es 'cambio_estado', puedes actualizar status aquí

        return new ServiceActionResource($action);
    }
}
