<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Accounting\Models\Invoice;

class InvoiceController extends Controller
{
    public function index()
    {
        return Invoice::with('contract')->paginate(15);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'contract_id'     => 'required|exists:contracts,contract_id',
            'issue_date'      => 'required|date',
            'amount'          => 'required|numeric',
            'currency'        => 'required|string|size:3',
            'document_number' => 'required|string|unique:invoices,document_number',
            'sunat_status'    => 'required|in:pendiente,enviado,aceptado,observado,rechazado',
        ]);

        return Invoice::create($data);
    }

    public function show(Invoice $invoice)
    {
        return $invoice->load('contract');
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'contract_id'     => 'sometimes|exists:contracts,contract_id',
            'issue_date'      => 'sometimes|date',
            'amount'          => 'sometimes|numeric',
            'currency'        => 'sometimes|string|size:3',
            'document_number' => 'required|string|unique:invoices,document_number,' . $invoice->invoice_id . ',invoice_id',
            'sunat_status'    => 'sometimes|in:pendiente,enviado,aceptado,observado,rechazado',
        ]);

        $invoice->update($data);
        return $invoice->load('contract');
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();
        return response()->noContent();
    }
}
