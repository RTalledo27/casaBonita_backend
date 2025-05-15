<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Http\Requests\StoreAddressRequest;
use Modules\CRM\Http\Requests\UpdateAddressRequest;
use Modules\CRM\Http\Resources\AddressResource;
use Modules\CRM\Models\Address;
use Modules\CRM\Repositories\AddressRepository;

class AddressController extends Controller
{


    //CONSTRUCTOR
    public function __construct(private AddressRepository $repo)
    {
        $this->middleware('auth:sanctum');
        $this->authorizeResource(Address::class, 'address');
    }
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = $request->only(['client_id', 'city', 'country', 'per_page']);
        return AddressResource::collection($this->repo->paginate($data));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('crm::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAddressRequest $req)
    {
        return new AddressResource($this->repo->create($req->validated()));
    }
    /**
     * Show the specified resource.
     */
    public function show(Address $address)
    {
        return new AddressResource($address->load('client'));
    }

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
    public function update(UpdateAddressRequest $req, Address $address)
    {
        return new AddressResource($this->repo->update($address, $req->validated()));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Address $address)
    {
        $address->delete();
        return response()->json(['message' => 'Address deleted successfully.'], 200);

    }
}
