<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Integrations\Models\DigitalSignature;

class DigitalSignatureController extends Controller
{
    public function index()
    {
        return DigitalSignature::paginate(15);
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'entity'      => 'required|string|max:60',
            'entity_id'   => 'required|integer',
            'hash'        => 'required|string|size:64',
            'certificate' => 'required|string',
        ]);
        return DigitalSignature::create($data);
    }

    public function show(DigitalSignature $digitalSignature)
    {
        return $digitalSignature;
    }

    public function destroy(DigitalSignature $digitalSignature)
    {
        $digitalSignature->delete();
        return response()->noContent();
    }
}
