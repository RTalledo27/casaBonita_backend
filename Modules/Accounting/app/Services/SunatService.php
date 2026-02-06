<?php

namespace Modules\Accounting\Services;

use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Greenter\Model\Sale\Invoice as GreenterInvoice;
use Greenter\Model\Sale\Note as GreenterNote;
use Greenter\Model\Sale\SaleDetail;
use Greenter\Model\Sale\Legend;
use Greenter\Model\Client\Client as GreenterClient;
use Greenter\Model\Company\Company;
use Greenter\Model\Company\Address;
use Modules\Accounting\Models\Invoice;
use Modules\Accounting\Models\InvoiceItem;
use Modules\Accounting\Models\InvoiceSeries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class SunatService //servicio sunat
{
    protected See $see;
    protected string $environment;
    protected Company $company;

    public function __construct()
    {
        $this->environment = config('sunat.environment', 'beta');
        $this->initializeSee();
        $this->initializeCompany();
    }

    /**
     * Inicializa el cliente SUNAT (Greenter See)
     */
    protected function initializeSee(): void
    {
        $this->see = new See();
        
        // Configurar endpoint según ambiente
        if ($this->environment === 'beta') {
            $this->see->setService(SunatEndpoints::FE_BETA);
        } else {
            $this->see->setService(SunatEndpoints::FE_PRODUCCION);
        }

        // Cargar certificado si existe
        $certPath = config('sunat.cert_path');
        $certPassword = config('sunat.cert_password');
        
        if ($this->isConfigured()) {
            $certPath = config('sunat.cert_path');
            if (!$certPath) $certPath = 'certs/certificado.p12';
            
            $certPassword = config('sunat.cert_password');
            
            // Garantizar password en caso de cache corrupta de entorno local
            if (empty($certPassword)) {
                $certPassword = 'Casabonita25'; 
            }

            try {
                $p12 = file_get_contents(storage_path($certPath));
                $certs = [];
                
                if (openssl_pkcs12_read($p12, $certs, $certPassword)) {
                    $certificate = $certs['cert'];
                    $privateKey = $certs['pkey'];
                    
                    // Greenter (SignedXml) requiere llaves concatenadas para setCertificate
                    $combinedPem = $privateKey . "\n" . $certificate;
                    $this->see->setCertificate($combinedPem);

                    // Configurar Credenciales SOL
                    $this->see->setClaveSOL(
                        config('sunat.ruc'),
                        config('sunat.sol_user'),
                        config('sunat.sol_pass')
                    );
                } else {
                    Log::error("SunatService: No se pudo leer el P12. Verificar contraseña.");
                }
            } catch (Exception $e) {
                Log::error("SunatService: Error cargando certificado: " . $e->getMessage());
            }
        } else {
            Log::warning('SunatService: Modo demo activo (Certificado no configurado).');
        }
    }

    /**
     * Inicializa datos de la empresa
     */
    protected function initializeCompany(): void
    {
        $address = (new Address())
            ->setUbigueo(config('sunat.ubigeo', '200101'))
            ->setDepartamento('PIURA')
            ->setProvincia('PIURA')
            ->setDistrito('PIURA')
            ->setUrbanizacion('SANTA ISABEL')
            ->setDireccion(config('sunat.direccion', 'JR. SAN CRISTOBAL NRO. 217 URB. SANTA ISABEL'));

        $this->company = (new Company())
            ->setRuc(config('sunat.ruc', '20613704214'))
            ->setRazonSocial(config('sunat.razon_social', 'CASA BONITA GRAU S.A.C.'))
            ->setNombreComercial(config('sunat.nombre_comercial', 'CASA BONITA'))
            ->setAddress($address);
    }

    /**
     * Emite una boleta o factura
     */
    public function emitInvoice(Invoice $invoice): array
    {
        try {
            // Verificar si tenemos certificado cargado
            if (!$this->isConfigured()) {
                Log::warning("SunatService: Emisión simulada para {$invoice->full_number} (Falta certificado)");
                return $this->simulateEmission($invoice);
            }

            // Obtener serie y correlativo
            $seriesData = InvoiceSeries::getNextCorrelative(
                $invoice->document_type, 
                $this->environment
            );
            
            $invoice->series = $seriesData['series'];
            $invoice->correlative = $seriesData['correlative'];
            $invoice->document_number = $invoice->full_number;
            
            // Construir documento Greenter
            $greenterDoc = $this->buildGreenterInvoice($invoice);
            
            // Generar XML
            $xml = $this->see->getXmlSigned($greenterDoc);
            $invoice->xml_content = $xml;
            $invoice->xml_hash = $this->extractHashFromXml($xml);
            
            // Generar QR
            $invoice->qr_code = $this->generateQrContent($invoice);
            
            // Enviar a SUNAT
            $result = $this->see->send($greenterDoc);
            
            if ($result->isSuccess()) {
                $cdr = $result->getCdrResponse();
                $invoice->sunat_status = Invoice::STATUS_ACEPTADO;
                $invoice->cdr_code = $cdr->getCode();
                $invoice->cdr_description = $cdr->getDescription();
                // Base64 encode para guardar binario en columna TEXT
                $invoice->cdr_content = base64_encode($result->getCdrZip());
                $invoice->sent_at = now();
                
                // Guardar XML en storage
                $this->saveXmlToStorage($invoice);
                
                Log::info("SunatService: Comprobante {$invoice->full_number} aceptado por SUNAT");
            } else {
                $invoice->sunat_status = Invoice::STATUS_RECHAZADO;
                $invoice->cdr_code = $result->getError()->getCode();
                $invoice->cdr_description = $result->getError()->getMessage();
                
                Log::error("SunatService: Error al enviar {$invoice->full_number}: " . $result->getError()->getMessage());
            }
            
            $invoice->save();
            
            return [
                'success' => $result->isSuccess(),
                'invoice' => $invoice,
                'message' => $invoice->cdr_description,
            ];
            
        } catch (Exception $e) {
            Log::error("SunatService: Exception al emitir: " . $e->getMessage());
            
            $invoice->sunat_status = Invoice::STATUS_RECHAZADO;
            $invoice->cdr_description = $e->getMessage();
            $invoice->save();
            
            return [
                'success' => false,
                'invoice' => $invoice,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Construye el objeto Greenter Invoice
     */
    protected function buildGreenterInvoice(Invoice $invoice): GreenterInvoice
    {
        // Cliente
        $client = (new GreenterClient())
            ->setTipoDoc($invoice->client_document_type === '6' ? '6' : '1')
            ->setNumDoc($invoice->client_document_number)
            ->setRznSocial($invoice->client_name)
            ->setAddress((new Address())->setDireccion($invoice->client_address ?? '-'));

        // Detalles
        $details = [];
        $igvTotal = 0;
        $subtotal = 0;
        
        foreach ($invoice->items as $index => $item) {
            $detail = (new SaleDetail())
                ->setCodProducto($item->product_code ?? 'P' . str_pad($index + 1, 3, '0', STR_PAD_LEFT))
                ->setUnidad($item->unit_code)
                ->setCantidad($item->quantity)
                ->setMtoValorUnitario($item->unit_price)
                ->setDescripcion($item->description)
                ->setMtoBaseIgv($item->subtotal)
                ->setPorcentajeIgv($item->igv_percentage)
                ->setIgv($item->igv_amount)
                ->setTipAfeIgv($item->igv_type)
                ->setMtoValorVenta($item->subtotal)
                ->setMtoPrecioUnitario($item->unit_price_with_igv);
            
            $details[] = $detail;
            $igvTotal += $item->igv_amount;
            $subtotal += $item->subtotal;
        }

        // Leyenda
        $legend = (new Legend())
            ->setCode('1000')
            ->setValue($this->numberToWords($invoice->total));

        // Documento
        $greenterInvoice = (new GreenterInvoice())
            ->setUblVersion('2.1')
            ->setTipoOperacion('0101') // Venta interna
            ->setTipoDoc($invoice->document_type)
            ->setSerie($invoice->series)
            ->setCorrelativo($invoice->correlative)
            ->setFechaEmision($invoice->issue_date)
            ->setTipoMoneda($invoice->currency ?? 'PEN')
            ->setCompany($this->company)
            ->setClient($client)
            ->setMtoOperGravadas($subtotal)
            ->setMtoIGV($igvTotal)
            ->setTotalImpuestos($igvTotal)
            ->setValorVenta($subtotal)
            ->setSubTotal($invoice->total)
            ->setMtoImpVenta($invoice->total)
            ->setDetails($details)
            ->setLegends([$legend]);

        // Actualizar montos en invoice
        $invoice->subtotal = $subtotal;
        $invoice->igv = $igvTotal;

        return $greenterInvoice;
    }

    /**
     * Emite una nota de crédito
     */
    public function emitCreditNote(Invoice $creditNote, Invoice $originalInvoice, string $reason): array
    {
        try {
            $seriesData = InvoiceSeries::getNextCorrelativeForNote(
                Invoice::TYPE_NOTA_CREDITO,
                $originalInvoice,
                $this->environment
            );
            
            $creditNote->series = $seriesData['series'];
            $creditNote->correlative = $seriesData['correlative'];
            $creditNote->document_number = $creditNote->full_number;
            $creditNote->related_invoice_id = $originalInvoice->invoice_id;
            $creditNote->void_reason = $reason;
            
            // Construir nota Greenter
            $greenterNote = $this->buildGreenterCreditNote($creditNote, $originalInvoice, $reason);
            
            // Generar XML y enviar
            $xml = $this->see->getXmlSigned($greenterNote);
            $creditNote->xml_content = $xml;
            $creditNote->xml_hash = $this->extractHashFromXml($xml);
            $creditNote->qr_code = $this->generateQrContent($creditNote);
            
            $result = $this->see->send($greenterNote);
            
            if ($result->isSuccess()) {
                $cdr = $result->getCdrResponse();
                $creditNote->sunat_status = Invoice::STATUS_ACEPTADO;
                $creditNote->cdr_code = $cdr->getCode();
                $creditNote->cdr_description = $cdr->getDescription();
                // Base64 encode para guardar binario en columna TEXT
                $creditNote->cdr_content = base64_encode($result->getCdrZip());
                $creditNote->sent_at = now();
            } else {
                $creditNote->sunat_status = Invoice::STATUS_RECHAZADO;
                $creditNote->cdr_code = $result->getError()->getCode();
                $creditNote->cdr_description = $result->getError()->getMessage();
            }
            
            $creditNote->save();
            
            return [
                'success' => $result->isSuccess(),
                'invoice' => $creditNote,
                'message' => $creditNote->cdr_description,
            ];
            
        } catch (Exception $e) {
            Log::error("SunatService: Error en nota de crédito: " . $e->getMessage());
            return [
                'success' => false,
                'invoice' => $creditNote,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Construye nota de crédito Greenter
     */
    protected function buildGreenterCreditNote(Invoice $creditNote, Invoice $original, string $reason): GreenterNote
    {
        $client = (new GreenterClient())
            ->setTipoDoc($creditNote->client_document_type === '6' ? '6' : '1')
            ->setNumDoc($creditNote->client_document_number)
            ->setRznSocial($creditNote->client_name);

        $details = [];
        foreach ($creditNote->items as $item) {
            $details[] = (new SaleDetail())
                ->setCodProducto($item->product_code ?? 'P001')
                ->setUnidad($item->unit_code)
                ->setCantidad($item->quantity)
                ->setMtoValorUnitario($item->unit_price)
                ->setDescripcion($item->description)
                ->setMtoBaseIgv($item->subtotal)
                ->setPorcentajeIgv($item->igv_percentage)
                ->setIgv($item->igv_amount)
                ->setTipAfeIgv($item->igv_type)
                ->setMtoValorVenta($item->subtotal)
                ->setMtoPrecioUnitario($item->unit_price_with_igv);
        }

        $legend = (new Legend())
            ->setCode('1000')
            ->setValue($this->numberToWords($creditNote->total));

        return (new GreenterNote())
            ->setUblVersion('2.1')
            ->setTipoDoc(Invoice::TYPE_NOTA_CREDITO)
            ->setSerie($creditNote->series)
            ->setCorrelativo($creditNote->correlative)
            ->setFechaEmision($creditNote->issue_date)
            ->setTipDocAfectado($original->document_type)
            ->setNumDocfectado($original->full_number)
            ->setCodMotivo('01') // Anulación de la operación
            ->setDesMotivo($reason)
            ->setTipoMoneda($creditNote->currency ?? 'PEN')
            ->setCompany($this->company)
            ->setClient($client)
            ->setMtoOperGravadas($creditNote->subtotal)
            ->setMtoIGV($creditNote->igv)
            ->setTotalImpuestos($creditNote->igv)
            ->setMtoImpVenta($creditNote->total)
            ->setDetails($details)
            ->setLegends([$legend]);
    }

    /**
     * Guarda XML en storage
     */
    protected function saveXmlToStorage(Invoice $invoice): void
    {
        $path = "sunat/xml/{$this->environment}/{$invoice->series}/{$invoice->full_number}.xml";
        Storage::put($path, $invoice->xml_content);
    }

    /**
     * Extrae hash del XML firmado
     */
    protected function extractHashFromXml(string $xml): string
    {
        preg_match('/<ds:DigestValue>(.+?)<\/ds:DigestValue>/', $xml, $matches);
        return $matches[1] ?? '';
    }

    /**
     * Genera contenido del QR
     */
    protected function generateQrContent(Invoice $invoice): string
    {
        return implode('|', [
            config('sunat.ruc'),
            $invoice->document_type,
            $invoice->series,
            $invoice->correlative,
            $invoice->igv,
            $invoice->total,
            $invoice->issue_date->format('Y-m-d'),
            $invoice->client_document_type,
            $invoice->client_document_number,
            $invoice->xml_hash ?? '',
        ]);
    }

    /**
     * Convierte número a palabras (simplificado)
     */
    protected function numberToWords(float $number): string
    {
        // Implementación simplificada
        $formatter = new \NumberFormatter('es_PE', \NumberFormatter::SPELLOUT);
        $intPart = floor($number);
        $decPart = round(($number - $intPart) * 100);
        
        $words = strtoupper($formatter->format($intPart));
        $words .= ' CON ' . str_pad($decPart, 2, '0', STR_PAD_LEFT) . '/100 SOLES';
        
        return $words;
    }

    /**
     * Consulta estado de un comprobante en SUNAT
     */
    public function checkStatus(Invoice $invoice): array
    {
        // TODO: Implementar consulta de estado
        return [
            'success' => true,
            'status' => $invoice->sunat_status,
        ];
    }

    /**
     * Obtiene el PDF de un comprobante
     */
    /**
     * Obtiene el PDF de un comprobante (Genera on-the-fly si no existe)
     */
    public function getPdfPath(Invoice $invoice): ?string
    {
        if ($invoice->pdf_path && Storage::exists($invoice->pdf_path)) {
            return Storage::path($invoice->pdf_path);
        }

        // Intento de generación automática
        try {
            return $this->generatePdf($invoice);
        } catch (Exception $e) {
            Log::error("SunatService: Error generando PDF: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Genera el archivo PDF físico
     */
    public function generatePdf(Invoice $invoice): string
    {
        $greenterDoc = $this->buildGreenterInvoice($invoice);
        
        // Configurar Reporte HTML
        $templateDir = base_path('Modules/Accounting/resources/views/pdf');
        $templateFile = 'invoice.html.twig';

        if (file_exists($templateDir . '/' . $templateFile)) {
            $report = new \Greenter\Report\HtmlReport($templateDir);
            $report->setTemplate($templateFile);
        } else {
            $report = new \Greenter\Report\HtmlReport();
            $resolver = new \Greenter\Report\Resolver\DefaultTemplateResolver();
            $report->setTemplate($resolver->getTemplate($greenterDoc));
        }

        // Cargar logo
        $logoPath = public_path('img/logo.png');
        $logo = file_exists($logoPath) ? file_get_contents($logoPath) : null;

        $params = [
            'system' => [
                'logo' => $logo, 
                'hash' => $invoice->xml_hash ?? '',
            ],
            'user' => [
                // 'extra' => 'Información Adicional'
            ]
        ];

        // Renderizar PDF
        // NOTA: Requiere wkhtmltopdf instalado en el sistema
        $pdfReport = new \Greenter\Report\PdfReport($report);
        // Configurar ruta del binario desde config
        $binPath = config('sunat.wkhtmltopdf_path');
        if ($binPath && file_exists($binPath)) {
            $pdfReport->setBinPath($binPath);
        } 
        
        $pdfContent = $pdfReport->render($greenterDoc, $params);

        if (!$pdfContent) {
            throw new Exception("El contenido del PDF vacío. Verifica wkhtmltopdf.");
        }

        $path = "sunat/pdf/{$this->environment}/{$invoice->series}/{$invoice->full_number}.pdf";
        Storage::put($path, $pdfContent);
        
        $invoice->pdf_path = $path;
        $invoice->save();

        return Storage::path($path);
    }

    /**
     * Genera representación HTML del comprobante
     */
    public function generateHtml(Invoice $invoice): string
    {
        $greenterDoc = $this->buildGreenterInvoice($invoice);
        
        // Configurar Reporte HTML
        $templateDir = base_path('Modules/Accounting/resources/views/pdf');
        $templateFile = 'invoice.html.twig';

        if (file_exists($templateDir . '/' . $templateFile)) {
            $report = new \Greenter\Report\HtmlReport($templateDir);
            $report->setTemplate($templateFile);
        } else {
            $report = new \Greenter\Report\HtmlReport();
            $resolver = new \Greenter\Report\Resolver\DefaultTemplateResolver();
            $report->setTemplate($resolver->getTemplate($greenterDoc));
        }

        // Cargar logo
        $logoPath = public_path('img/logo.png');
        $logo = file_exists($logoPath) ? file_get_contents($logoPath) : null;

        $params = [
            'system' => [
                'logo' => $logo, 
                'hash' => $invoice->xml_hash ?? '',
            ],
            'user' => []
        ];

        return $report->render($greenterDoc, $params);
    }

    protected function isConfigured(): bool
    {
        // 1. Intentar desde config
        $certPath = config('sunat.cert_path');
        if ($certPath && file_exists(storage_path($certPath))) {
            return true;
        }

        // 2. Intentar ruta hardcodeada (fallback)
        $fallbackPath = storage_path('certs/certificado.p12');
        if (file_exists($fallbackPath)) {
            // Actualizar config en runtime para que el resto del servicio lo use
            config(['sunat.cert_path' => 'certs/certificado.p12']);
            return true;
        }

        return false;
    }

    protected function simulateEmission(Invoice $invoice): array
    {
        // Obtener correlativo real para que se guarde bien en BD
        $seriesData = InvoiceSeries::getNextCorrelative(
            $invoice->document_type, 
            $this->environment
        );
        
        $invoice->series = $seriesData['series'];
        $invoice->correlative = $seriesData['correlative'];
        $invoice->document_number = $invoice->full_number;

        // Simular respuesta exitosa
        $invoice->sunat_status = Invoice::STATUS_ACEPTADO;
        $invoice->cdr_code = '0';
        $invoice->cdr_description = 'ACEPTADO (SIMULADO)';
        $invoice->sent_at = now();
        $invoice->xml_hash = 'SIMULATED_HASH_'.uniqid();
        $invoice->qr_code = 'QR_SIMULADO|'.$invoice->full_number;
        
        $invoice->save();

        return [
            'success' => true,
            'invoice' => $invoice,
            'message' => 'Comprobante emitido correctamente (Modo Simulación)',
        ];
    }
}
