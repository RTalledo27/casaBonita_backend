<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Http\Requests\StoreAddressRequest;
use Modules\CRM\Http\Requests\UpdateAddressRequest;
use Modules\CRM\Models\Address;
use Modules\CRM\Repositories\AddressRepository;
use Modules\CRM\Transformers\AddressResource;
use Modules\services\PusherNotifier;

class AddressController extends Controller
{


    public function __construct(
        private AddressRepository $addresses,
        private PusherNotifier $pusher
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:crm.addresses.view')->only(['index', 'show']);
        $this->middleware('permission:crm.addresses.store')->only('store');
        $this->middleware('permission:crm.addresses.update')->only('update');
        $this->middleware('permission:crm.addresses.delete')->only('destroy');
    }

    /**
     * Listar todas las direcciones (con paginación si es necesario).
     */
    public function index(Request $request)
    {
        return AddressResource::collection($this->addresses->paginate($request->all()));
    }

    /**
     * Registrar una nueva dirección.
     */
    public function store(StoreAddressRequest $req)
    {
        $address = $this->addresses->create($req->validated());
        $this->pusher->notify('address', 'created', ['address' => new AddressResource($address)]);
        return new AddressResource($address);
    }

    /**
     * Mostrar detalles de una dirección.
     */
    public function show(Address $address)
    {
        return new AddressResource($address);
    }

    /**
     * Actualizar una dirección existente.
     */
    public function update(UpdateAddressRequest $req, Address $address)
    {
        $updated = $this->addresses->update($address, $req->validated());
        $this->pusher->notify('address', 'updated', ['address' => new AddressResource($updated)]);
        return new AddressResource($updated);
    }

    /**
     * Eliminar una dirección del sistema.
     */
    public function destroy(Address $address)
    {
        $id = $address->address_id;
        $this->addresses->delete($address);
        $this->pusher->notify('address', 'deleted', ['address' => ['address_id' => $id]]);
        return response()->json(['message' => 'Dirección eliminada', 'address_id' => $id]);
    }
}
