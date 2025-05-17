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
        $this->middleware('permission:crm.clients.view')->only(['index', 'show', 'summary']);
        $this->middleware('permission:crm.clients.create')->only('store');
        $this->middleware('permission:crm.clients.update')->only('update');
        $this->middleware('permission:crm.clients.delete')->only('destroy');
        $this->authorizeResource(Client::class, 'client');
    }

    /**
     * Display a listing of the resource.
     */
    /** GET /crm/clients */
    /**
     * @group CRM - Clientes
     * Listar clientes registrados en el sistema.
     *
     * @queryParam search string Buscar por nombre, email, etc. Example: Juan
     * @queryParam type string Filtrar por tipo (lead/client). Example: lead
     * @queryParam sort_by string Campo de ordenamiento. Example: first_name
     * @queryParam sort_dir string Dirección de orden (asc/desc). Example: desc
     * @queryParam per_page integer Resultados por página. Example: 10
     *
     * @response 200
     */

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
    /**
     * Crear un nuevo cliente.
     *
     * @bodyParam first_name string required. Nombre. Example: Ana
     * @bodyParam last_name string required. Apellido. Example: Pérez
     * @bodyParam doc_type string required. Tipo de documento. Example: DNI
     * @bodyParam doc_number string required. Número de documento. Example: 12345678
     * @bodyParam primary_phone string required. Teléfono principal. Example: 987654321
     * @bodyParam spouse_id integer ID del cónyuge (opcional). Example: 3
     * @bodyParam addresses array Lista de direcciones. Example: [{"line1":"Av. Lima 123", "city":"Lima"}]
     *
     * @response 201 {
     *   "data": {
     *     "client_id": 1,
     *     "first_name": "Ana",
     *     ...
     *   }
     * }
     */

    public function store(StoreClientRequest $req)
    {
        return new ClientResource($this->clients->create($req->validated()));
    }

    


    /**
     * Store a newly created resource in storage.
     */
    /** GET /crm/clients/{client} */
    /**
     * Mostrar un cliente específico con todas sus relaciones.
     *
     * @urlParam client int required ID del cliente. Example: 1
     * @response 200
     */

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
    /**
     * Actualizar información de un cliente.
     *
     * @urlParam client int required ID del cliente. Example: 1
     * @bodyParam first_name string Nombre. Example: Ana
     * @response 200
     */

    public function update(UpdateClientRequest $req, Client $client)
    {
        return new ClientResource($this->clients->update($client, $req->validated()));
    }

   


    /**
     * Remove the specified resource from storage.
     */

    /** DELETE /crm/clients/{client} */
    /**
     * Eliminar un cliente del sistema.
     *
     * @urlParam client int required ID del cliente. Example: 1
     * @response 204
     */

    public function destroy(Client $client)
    {
        $this->clients->delete($client);
        return response()->noContent();
    }

    // ────────────────────────────────
    //  Gestión de cónyuges
    // ────────────────────────────────

    /** GET /crm/clients/{client}/spouses */
    /**
     * Obtener lista de cónyuges asociados al cliente.
     *
     * @urlParam client int required ID del cliente. Example: 1
     * @response 200
     */

    public function spouses(Client $client)
    {
        $spouses = $client->spouses()->with(['addresses', 'interactions'])->get();

        return ClientResource::collection($spouses);
    }

  
  

    /**
     * Agregar un cónyuge al cliente.
     *
     * @urlParam client int required ID del cliente. Example: 1
     * @bodyParam partner_id int required ID del cónyuge a vincular. Example: 4
     * @response 200
     */
    /** POST /crm/clients/{client}/spouses */


    public function addSpouse(SpouseRequest $req, Client $client)
    {
        $spouseId = $req->input('partner_id');
        $this->clients->addSpouse($client, $spouseId);
        return response()->json([
            'message' => 'Conyugue agregado correctamente',
        ]);
    }

    /** DELETE /crm/clients/{client}/spouses/{spouse} */
    /**
     * Eliminar un cónyuge del cliente.
     *
     * @urlParam client int required ID del cliente. Example: 1
     * @urlParam spouse int required ID del cónyuge. Example: 4
     * @response 200
     */

    public function removeSpouse(Client $client, Client $partner)
    {
        $this->clients->removeSpouse($client, $partner->client_id);
        return response()->json([
            'message' => 'Conyugue eliminado correctamente',
        ], 200);
    }


    // ────────────────────────────────
    // Reportes y exportación
    // ────────────────────────────────

    /** GET /crm/clients/report.csv */
    /**
     * Exportar reporte CSV de clientes.
     *
     * @queryParam type string Filtrar por tipo. Example: client
     * @queryParam date_from date Fecha desde. Example: 2024-01-01
     * @queryParam date_to date Fecha hasta. Example: 2024-12-31
     * @response file
     */

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
    /**
     * Obtener resumen de relaciones del cliente.
     *
     * @urlParam client int required ID del cliente. Example: 1
     * @response 200 {
     *   "client_id": 1,
     *   "total_addresses": 2,
     *   "total_interactions": 3
     * }
     */

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
