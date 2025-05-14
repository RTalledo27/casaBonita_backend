<?php

namespace Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\CRM\Models\Address;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Address::with('client')
            ->paginate(15);
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
    public function store(Request $request) {
        $data = $request->validate([
            'client_id' => 'required|exists:clients,client_id',
            'line1'     => 'required|string|max:120',
            'line2'     => 'nullable|string|max:120',
            'city'      => 'required|string|max:60',
            'state'     => 'nullable|string|max:60',
            'country'   => 'required|string|max:60',
            'zip_code'  => 'nullable|string|max:15',
        ]);
        return Address::create($data);
    }

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('crm::show');
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
    public function update(Request $request, Address $address) {
        $address->update($request->all());
        return $address;

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
