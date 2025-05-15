<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Modules\CRM\Http\Requests\{
    StoreClientRequest,
    UpdateClientRequest,
    SpouseRequest
};

use Modules\CRM\Repositories\{
    ClientRepository,
    AddressRepository,
    CrmInteractionRepository
};
use Modules\CRM\Models\Client;
use Modules\CRM\Transformers\ClientResource;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{


    //CONSTRUCTOR
    public function __construct(
        private ClientRepository         $clients,
        private AddressRepository        $addresses,
        private CrmInteractionRepository $interactions,
    ) {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Client::class, 'client');
    }

    /**
     * Display a listing of the resource.
     */
    /** GET /crm/clients */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'type', 'sort_by', 'sort_dir', 'per_page']);
        return ClientResource::collection($this->clients->paginate($filters));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
     
    }

    /** POST /crm/clients */
    public function store(StoreClientRequest $req)
    {
        return new ClientResource($this->clients->create($req->validated()));
    }

    


    /**
     * Store a newly created resource in storage.
     */
    /** GET /crm/clients/{client} */
    public function show(Client $client)
    {
        // carga lazy de relaciones
        $client->load(['addresses', 'interactions', 'spouses']);
        return new ClientResource($client);
    }
    /**
     * Show the specified resource.
     */
   

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('crm::edit');
    }

    /**
     * Update the specified resource in storage.
     */


    /** PUT/PATCH /crm/clients/{client} */
    public function update(UpdateClientRequest $req, Client $client)
    {
        return new ClientResource($this->clients->update($client, $req->validated()));
    }

   


    /**
     * Remove the specified resource from storage.
     */

    /** DELETE /crm/clients/{client} */
    public function destroy(Client $client)
    {
        $this->clients->delete($client);
        return response()->noContent();
    }

    // ────────────────────────────────
    //  Gestión de cónyuges
    // ────────────────────────────────

    /** GET /crm/clients/{client}/spouses */
    public function spouses(Client $client)
    {
        return ClientResource::collection($client->spouses);
    }

    /** POST /crm/clients/{client}/spouses */
    public function storeSpouse(SpouseRequest $req, Client $client)
    {
        $spouseId = $req->input('partner_id');
        $this->clients->addSpouse($client, $spouseId);
        return response()->json([
            'message' => 'Conyugue agregado correctamente',
        ])->status(200);
    }

    /** DELETE /crm/clients/{client}/spouses/{spouse} */
    public function destroySpouse(Client $client, Client $partner)
    {
        $this->clients->removeSpouse($client, $partner->client_id);
        return response()->json([
            'message' => 'Conyugue eliminado correctamente',
        ])->status(200);
    }


    // ────────────────────────────────
    // Reportes y exportación
    // ────────────────────────────────

    /** GET /crm/clients/report.csv */
    public function exportCsv(Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($request) {
            $handle = fopen('php://output', 'w');
            // encabezados
            fputcsv($handle, ['ID', 'Nombre', 'Documento', 'Email', 'Tipo', 'Creado']);
            // iterar todos o filtrados
            $filters = $request->only(['type', 'date_from', 'date_to']);
            $this->clients->all($filters)
                ->each(fn($c) => fputcsv($handle, [
                    $c->client_id,
                    "{$c->first_name} {$c->last_name}",
                    "{$c->doc_type}-{$c->doc_number}",
                    $c->email,
                    $c->type,
                    $c->created_at->toDateString(),
                ]));
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="clients_report.csv"');

        return $response;
    }

    /** GET /crm/clients/{client}/summary */
    public function summary(Client $client)
    {
        $totalAddresses   = $client->addresses()->count();
        $totalInteractions = $client->interactions()->count();
        return response()->json([
            'client_id'          => $client->client_id,
            'total_addresses'    => $totalAddresses,
            'total_interactions' => $totalInteractions,
        ]);
    }

}
