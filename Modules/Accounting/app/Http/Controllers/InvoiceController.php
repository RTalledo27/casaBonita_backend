<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceItem;
use Modules\Accounting\Models\InvoiceSeries;
use Modules\Accounting\Services\SunatService;
use Modules\CRM\Models\Client;

class InvoiceController extends Controller
{
    protected ?SunatService $sunatService = null;

    public function __construct()
    {
        // Se instanciará bajo demanda
    }

    protected function getSunatService(): SunatService
    {
        if (!$this->sunatService) {
            $this->sunatService = app(SunatService::class);
        }
        return $this->sunatService;
    }

    /**
     * Lista de comprobantes con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['items', 'contract.client'])
            ->orderBy('invoice_id', 'desc');

        // Filtros
        if ($request->has('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->has('sunat_status')) {
            $query->where('sunat_status', $request->sunat_status);
        }

        if ($request->has('date_from')) {
            $query->whereDate('issue_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('issue_date', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('series', 'like', "%{$search}%")
                  ->orWhere('correlative', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%")
                  ->orWhere('client_document_number', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate($request->get('per_page', 20)));
    }

    /**
     * Dashboard de facturación
     */
    public function dashboard(Request $request): JsonResponse
    {
        $today = now()->toDateString();
        $startOfMonth = now()->startOfMonth()->toDateString();

        $stats = [
            'today' => [
                'total' => Invoice::whereDate('issue_date', $today)->sum('total'),
                'count' => Invoice::whereDate('issue_date', $today)->count(),
                'accepted' => Invoice::whereDate('issue_date', $today)->where('sunat_status', 'aceptado')->count(),
                'rejected' => Invoice::whereDate('issue_date', $today)->where('sunat_status', 'rechazado')->count(),
            ],
            'month' => [
                'total' => Invoice::whereDate('issue_date', '>=', $startOfMonth)->sum('total'),
                'count' => Invoice::whereDate('issue_date', '>=', $startOfMonth)->count(),
                'boletas' => Invoice::whereDate('issue_date', '>=', $startOfMonth)->where('document_type', '03')->count(),
                'facturas' => Invoice::whereDate('issue_date', '>=', $startOfMonth)->where('document_type', '01')->count(),
            ],
            'pending' => Invoice::where('sunat_status', 'pendiente')->count(),
            'rejected' => Invoice::where('sunat_status', 'rechazado')->count(),
            'recent' => Invoice::with('items')
                ->orderBy('invoice_id', 'desc')
                ->limit(10)
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Emitir Boleta
     */
    public function emitBoleta(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_document_number' => 'required|string|max:15',
            'client_name' => 'required|string|max:200',
            'client_address' => 'nullable|string|max:300',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price_with_igv' => 'required|numeric|min:0.01',
            'items.*.unit_code' => 'nullable|string|max:3',
            'contract_id' => 'nullable|exists:contracts,contract_id',
            'payment_id' => 'nullable|exists:Modules\Sales\Models\Payment,payment_id',
            'issue_date' => 'nullable|date',
        ]);

        return $this->emitDocument($validated, Invoice::TYPE_BOLETA, '1');
    }

    /**
     * Emitir Factura
     */
    public function emitFactura(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_document_number' => 'required|string|size:11', // RUC debe ser 11 dígitos
            'client_name' => 'required|string|max:200',
            'client_address' => 'required|string|max:300',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price_with_igv' => 'required|numeric|min:0.01',
            'items.*.unit_code' => 'nullable|string|max:3',
            'contract_id' => 'nullable|exists:contracts,contract_id',
            'payment_id' => 'nullable|exists:Modules\Sales\Models\Payment,payment_id',
            'issue_date' => 'nullable|date',
        ]);

        return $this->emitDocument($validated, Invoice::TYPE_FACTURA, '6');
    }

    /**
     * Lógica común para emitir documentos
     */
    protected function emitDocument(array $data, string $documentType, string $clientDocType): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 1. Obtener serie activa
            $series = InvoiceSeries::where('document_type', $documentType)
                ->where('is_active', true)
                ->first();

            if (!$series) {
                throw new \Exception("No hay serie activa configurada para este tipo de documento.");
            }

            // 2. Incrementar correlativo (con bloqueo para evitar duplicados)
            $series->increment('current_correlative'); 
            $correlative = $series->current_correlative;

            // Crear invoice
            $invoice = new Invoice();
            $invoice->series = $series->series;
            $invoice->correlative = $correlative;
            $invoice->document_number = $series->series . '-' . $correlative; // Campo legacy requerido
            $invoice->document_type = $documentType;
            $invoice->client_document_type = $clientDocType;
            $invoice->client_document_number = $data['client_document_number'];
            $invoice->client_name = $data['client_name'];
            $invoice->client_address = $data['client_address'] ?? null;
            $invoice->issue_date = $data['issue_date'] ?? now();
            $invoice->currency = 'PEN';
            $invoice->sunat_status = Invoice::STATUS_PENDIENTE;
            $invoice->contract_id = $data['contract_id'] ?? null;
            $invoice->payment_id = $data['payment_id'] ?? null; // Agregar payment_id
            
            // Inicializar montos en 0 para evitar error SQL 1364 (NOT NULL sin default)
            $invoice->amount = 0.00;
            $invoice->total = 0.00;
            $invoice->subtotal = 0.00;
            $invoice->igv = 0.00;
            
            $invoice->save();

            // Crear items
            $totalAmount = 0;
            foreach ($data['items'] as $index => $itemData) {
                $item = InvoiceItem::fromPriceWithIgv(
                    $itemData['description'],
                    $itemData['quantity'],
                    $itemData['unit_price_with_igv'],
                    $itemData['unit_code'] ?? InvoiceItem::UNIT_UNIDAD
                );
                $item->invoice_id = $invoice->invoice_id;
                $item->order = $index + 1;
                $item->save();

                $totalAmount += $item->total;
            }

            // Actualizar totales
            $invoice->amount = $totalAmount;
            $invoice->total = $totalAmount;
            $invoice->subtotal = Invoice::calculateSubtotalFromTotal($totalAmount);
            $invoice->igv = $totalAmount - $invoice->subtotal;
            $invoice->save();

            // Recargar items
            $invoice->load('items');

            // Emitir a SUNAT (usando getter lazy)
            $result = $this->getSunatService()->emitInvoice($invoice);

            // Si falla SUNAT, no hacemos rollback de la factura, solo guardamos el error
            if (!$result['success']) {
                // Opcional: Loguear error o cambiar estado a ERROR
            }

            DB::commit();

            // Recargar items y ocultar binarios explícitamente para evitar error JSON
            $invoiceFresh = $invoice->fresh(['items']);
            $invoiceFresh->makeHidden(['xml_content', 'cdr_content', 'pdf_path', 'xml_hash']);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'invoice' => $invoiceFresh,
            ], $result['success'] ? 201 : 422);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al emitir comprobante: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Emitir Nota de Crédito
     */
    public function emitNotaCredito(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_invoice_id' => 'required|exists:invoices,invoice_id',
            'reason' => 'required|string|max:100',
            'items' => 'required|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price_with_igv' => 'required|numeric|min:0.01',
        ]);

        try {
            DB::beginTransaction();

            $originalInvoice = Invoice::findOrFail($validated['original_invoice_id']);

            // Crear nota de crédito
            $creditNote = new Invoice();
            $creditNote->document_type = Invoice::TYPE_NOTA_CREDITO;
            $creditNote->client_document_type = $originalInvoice->client_document_type;
            $creditNote->client_document_number = $originalInvoice->client_document_number;
            $creditNote->client_name = $originalInvoice->client_name;
            $creditNote->client_address = $originalInvoice->client_address;
            $creditNote->issue_date = now();
            $creditNote->currency = 'PEN';
            $creditNote->sunat_status = Invoice::STATUS_PENDIENTE;
            $creditNote->save();

            // Items
            $total = 0;
            foreach ($validated['items'] as $index => $itemData) {
                $item = InvoiceItem::fromPriceWithIgv(
                    $itemData['description'],
                    $itemData['quantity'],
                    $itemData['unit_price_with_igv']
                );
                $item->invoice_id = $creditNote->invoice_id;
                $item->order = $index + 1;
                $item->save();
                $total += $item->total;
            }

            $creditNote->total = $total;
            $creditNote->subtotal = Invoice::calculateSubtotalFromTotal($total);
            $creditNote->igv = $total - $creditNote->subtotal;
            $creditNote->save();

            $creditNote->load('items');

            // Emitir
            $result = $this->getSunatService()->emitCreditNote($creditNote, $originalInvoice, $validated['reason']);

            DB::commit();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['message'],
                'invoice' => $creditNote->fresh(['items']),
            ], $result['success'] ? 201 : 422);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al emitir nota de crédito: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ver detalle de comprobante
     */
    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json(
            $invoice->load(['items', 'contract.client', 'relatedInvoice', 'notes'])
        );
    }

    /**
     * Descargar XML
     */
    public function downloadXml(Invoice $invoice): mixed
    {
        if (!$invoice->xml_content) {
            return response()->json(['error' => 'XML no disponible'], 404);
        }

        return response($invoice->xml_content)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="' . $invoice->full_number . '.xml"');
    }

    /**
     * Descargar PDF
     */
    public function downloadPdf(Invoice $invoice): mixed
    {
        $path = $this->getSunatService()->getPdfPath($invoice);

        if ($path) {
            return response()->download($path, $invoice->full_number . '.pdf');
        }

        // Fallback: Si no hay PDF (falta wkhtmltopdf), mostramos HTML
        try {
            $html = $this->getSunatService()->generateHtml($invoice);
            return response($html)
                ->header('Content-Type', 'text/html');
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se pudo generar el comprobante: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reenviar a SUNAT
     */
    public function resend(Invoice $invoice): JsonResponse
    {
        if ($invoice->sunat_status === Invoice::STATUS_ACEPTADO) {
            return response()->json([
                'success' => false,
                'message' => 'El comprobante ya fue aceptado por SUNAT',
            ], 422);
        }

        $result = $this->sunatService->emitInvoice($invoice);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'invoice' => $invoice->fresh(['items']),
        ]);
    }

    /**
     * Listar series disponibles
     */
    public function listSeries(Request $request): JsonResponse
    {
        $environment = config('sunat.environment', 'beta');
        
        $series = InvoiceSeries::where('environment', $environment)
            ->where('is_active', true)
            ->get();

        return response()->json($series);
    }

    /**
     * Buscar cliente por DNI/RUC
     */
    public function searchClient(Request $request): JsonResponse
    {
        $document = $request->get('document');

        if (!$document) {
            return response()->json(['error' => 'Documento requerido'], 400);
        }

        // Buscar en base de datos local primero (CRM)
        $client = \Modules\CRM\Models\Client::where('doc_number', $document)->first();

        if ($client) {
            return response()->json([
                'found' => true,
                'source' => 'local',
                'client' => [
                    'document_type' => strlen($document) === 11 ? '6' : '1',
                    'document_number' => $client->doc_number,
                    'name' => $client->first_name . ' ' . $client->last_name,
                    'address' => $client->address_line_1 ?? '',
                    'email' => $client->email
                ],
            ]);
        }

        return response()->json([
            'found' => false,
            'message' => 'Cliente no encontrado',
        ]);
    }

    /**
     * Obtener pagos pendientes de facturar
     */
    public function getPendingPayments(Request $request): JsonResponse
    {
        $search = $request->input('search');

        // Buscar pagos que NO tengan factura asociada y estén confirmados/pagados
        $query = \Modules\Sales\Models\Payment::query()
            ->with(['contract.client', 'contract.lot.manzana'])
            ->doesntHave('invoices')
            // ->where('status', 'paid') // Descomentar cuando Payment tenga status
            ->orderBy('payment_date', 'desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->whereHas('contract.client', function($cq) use ($search) {
                    $cq->where('first_name', 'like', "%{$search}%")
                       ->orWhere('last_name', 'like', "%{$search}%")
                       ->orWhere('doc_number', 'like', "%{$search}%");
                })
                ->orWhere('reference', 'like', "%{$search}%");
            });
        }

        $payments = $query->take(20)->get();

        return response()->json($payments->map(function($payment) {
            // Construir descripción automática
            $description = "Pago Ref: " . $payment->reference;
            if ($payment->contract) {
                $lotName = $payment->contract->getLotName() ?? 'Sin Lote';
                $description = "Pago de cuota - Lote {$lotName}";
                if ($payment->schedule) {
                    $description .= " (Cuota {$payment->schedule->installment_number})";
                }
            }

            return [
                'payment_id' => $payment->payment_id,
                'amount' => (float)$payment->amount,
                'payment_date' => $payment->payment_date,
                'reference' => $payment->reference,
                'method' => $payment->method,
                'client_name' => $payment->contract ? $payment->contract->getClientName() : 'Cliente Desconocido',
                'client_document' => $payment->contract && $payment->contract->client ? $payment->contract->client->doc_number : '',
                'client_address' => $payment->contract && $payment->contract->client ? $payment->contract->client->address_line_1 : '',
                'description' => $description
            ];
        }));
    }
}

