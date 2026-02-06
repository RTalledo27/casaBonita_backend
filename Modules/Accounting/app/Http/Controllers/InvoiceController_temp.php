    public function searchClient(Request $request)
    {
        $document = $request->input('document');
        
        // 1. Buscar en BD local (CRM)
        $client = \Modules\CRM\Models\Client::where('document_number', $document)->first();
        
        if ($client) {
            return response()->json([
                'found' => true,
                'source' => 'local',
                'client' => [
                    'name' => $client->first_name . ' ' . $client->last_name,
                    'address' => $client->address_line_1,
                    'email' => $client->email
                ]
            ]);
        }
        
        // 2. TODO: Buscar en API SUNAT/Reniec externa si no está en local
        
        return response()->json(['found' => false]);
    }

    /**
     * Obtener pagos pendientes de facturar
     */
    public function getPendingPayments(Request $request)
    {
        $search = $request->input('search');

        $query = \Modules\Sales\Models\Payment::query()
            ->with(['contract.client', 'contract.lot.manzana'])
            ->doesntHave('invoices') // Solo pagos sin factura
            ->where('status', 'paid') // Asumiendo que hay un estado, o si no filtramos los reales
            ->orderBy('payment_date', 'desc');

        if ($search) {
            $query->whereHas('contract.client', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            })
            ->orWhere('reference', 'like', "%{$search}%");
        }

        $payments = $query->take(20)->get();

        return response()->json($payments->map(function($payment) {
            return [
                'payment_id' => $payment->payment_id,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date,
                'reference' => $payment->reference,
                'method' => $payment->method,
                'client_name' => $payment->contract ? $payment->contract->getClientName() : 'Cliente Desconocido',
                'client_document' => $payment->contract && $payment->contract->client ? $payment->contract->client->document_number : '',
                'client_address' => $payment->contract && $payment->contract->client ? $payment->contract->client->address_line_1 : '',
                'description' => $payment->contract 
                    ? "Pago cuota - " . $payment->contract->getLotName() 
                    : "Pago Ref: " . $payment->reference
            ];
        }));
    }
}
