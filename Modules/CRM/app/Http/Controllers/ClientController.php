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
use Modules\services\PusherNotifier;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientController extends Controller
{

    public function __construct(
        private ClientRepository $clients,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('can:crm.access');
        $this->middleware('permission:crm.clients.view')->only(['index', 'show', 'summary']);
        $this->middleware('permission:crm.clients.store')->only('store');
        $this->middleware('permission:crm.clients.update')->only('update');
        $this->middleware('permission:crm.clients.delete')->only('destroy');
        $this->middleware('permission:crm.clients.spouses.view')->only(['spouses']);
        $this->middleware('permission:crm.clients.spouses.create')->only(['addSpouse']);
        $this->middleware('permission:crm.clients.spouses.delete')->only(['removeSpouse']);
        $this->middleware('permission:crm.clients.export')->only(['exportCsv']);
        $this->authorizeResource(Client::class, 'client');   }

    /**
     * Listar clientes con filtros, orden y paginación.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'type', 'sort_by', 'sort_dir', 'per_page']);
        return ClientResource::collection($this->clients->paginate($filters));
    }

    /**
     * Crear un nuevo cliente.
     */
    public function store(StoreClientRequest $req)
    {
        $client = $this->clients->create($req->validated());
        $this->pusher->notify('client', 'created', ['client' => new ClientResource($client)]);
        return new ClientResource($client);
    }

    /**
     * Mostrar un cliente específico con sus relaciones.
     */
    public function show(Client $client)
    {
        $client->load(['addresses', 'interactions', 'spouses']);
        return new ClientResource($client);
    }

    /**
     * Actualizar un cliente existente.
     */
    public function update(UpdateClientRequest $req, Client $client)
    {
        $updated = $this->clients->update($client, $req->validated());
        $this->pusher->notify('client', 'updated', ['client' => new ClientResource($updated)]);
        return new ClientResource($updated);
    }

    /**
     * Eliminar un cliente del sistema.
     */
    public function destroy(Client $client)
    {
        $id = $client->client_id;
        $this->clients->delete($client);
        $this->pusher->notify('client', 'deleted', ['client' => ['client_id' => $id]]);
        return response()->json(['message' => 'Cliente eliminado', 'client_id' => $id]);
    }

    /**
     * Obtener la lista de cónyuges del cliente.
     */
    public function spouses(Client $client)
    {
        $spouses = $client->spouses()->with(['addresses', 'interactions'])->get();
        return ClientResource::collection($spouses);
    }

    /**
     * Agregar un cónyuge al cliente.
     */
    public function addSpouse(SpouseRequest $req, Client $client)
    {
        $this->clients->addSpouse($client, $req->partner_id);
        return response()->json(['message' => 'Conyugue agregado correctamente']);
    }

    /**
     * Eliminar un cónyuge del cliente.
     */
    public function removeSpouse(Client $client, Client $partner)
    {
        $this->clients->removeSpouse($client, $partner->client_id);
        return response()->json(['message' => 'Conyugue eliminado correctamente']);
    }

    /**
     * Exportar clientes a archivo CSV con filtros.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($request) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Nombre', 'Documento', 'Email', 'Tipo', 'Creado']);
            $filters = $request->only(['type', 'date_from', 'date_to']);
            $this->clients->all($filters)
                ->each(fn($c) => fputcsv($handle, [
                    $c->client_id,
                    "$c->first_name $c->last_name",
                    "$c->doc_type-$c->doc_number",
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

    /**
     * Obtener resumen del cliente: total de direcciones e interacciones.
     */
    public function summary(Client $client)
    {
        return response()->json([
            'client_id' => $client->client_id,
            'total_addresses' => $client->addresses()->count(),
            'total_interactions' => $client->interactions()->count(),
        ]);
    }
}
