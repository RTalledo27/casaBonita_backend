<?php

namespace Modules\ServiceDesk\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\ServiceDesk\Repositories\ServiceDeskDashRepository;
use Modules\ServiceDesk\Repositories\ServiceRequestRepository;
use Modules\ServiceDesk\Transformers\ServiceDeskDashResource;

class ServiceDeskController extends Controller
{
    protected $dashboardRepo;

    public function __construct(ServiceDeskDashRepository $dashboardRepo)
    {
        $this->dashboardRepo = $dashboardRepo;
    }

    public function dashboard(Request $request)
    {
        // Opcional: autoriza si tienes policy especial
        // $this->authorize('viewDashboard', ServiceRequest::class);
        $params = $request->query(); 

        $data = $this->dashboardRepo->getDashboardData($params);
        return new ServiceDeskDashResource($data);
    }
}
