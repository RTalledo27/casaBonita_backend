<?php

namespace Modules\Sales\Services;

use Illuminate\Support\Facades\Storage;
use Modules\Sales\Models\Contract;
use Spatie\Browsershot\Browsershot;

class ContractPdfService
{
    protected function previewHtml(Contract $contract): string
    {
        $reservation = $contract->reservation;
        $client = $reservation?->client;
        $lot = $reservation?->lot;
        $advisor = $contract->advisor;

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Contrato de Compraventa - {$contract->contract_number}</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .section { margin-bottom: 20px; }
                .financial-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .financial-table th, .financial-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .financial-table th { background-color: #f2f2f2; }
                .total-row { font-weight: bold; background-color: #f9f9f9; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>CONTRATO DE COMPRAVENTA</h1>
                <h2>No. {$contract->contract_number}</h2>
                <p>Fecha: {$contract->sign_date}</p>
            </div>

            <div class='section'>
                <h3>INFORMACIÓN DEL CLIENTE</h3>
                <p><strong>Nombre:</strong> {$client?->first_name} {$client?->last_name}</p>
                <p><strong>Email:</strong> {$client?->email}</p>
                <p><strong>Teléfono:</strong> {$client?->phone}</p>
            </div>

            <div class='section'>
                <h3>INFORMACIÓN DEL ASESOR</h3>
                <p><strong>Nombre:</strong> {$advisor?->first_name} {$advisor?->last_name}</p>
                <p><strong>Email:</strong> {$advisor?->email}</p>
            </div>

            <div class='section'>
                <h3>INFORMACIÓN DEL INMUEBLE</h3>
                <p><strong>Lote:</strong> {$lot?->lot_number}</p>
                <p><strong>Área:</strong> {$lot?->area} m²</p>
                <p><strong>Ubicación:</strong> {$lot?->block}</p>
            </div>

            <div class='section'>
                <h3>INFORMACIÓN FINANCIERA</h3>
                <table class='financial-table'>
                    <tr>
                        <th>Concepto</th>
                        <th>Monto ({$contract->currency})</th>
                        <th>Porcentaje</th>
                    </tr>
                    <tr>
                        <td>Precio Total</td>
                        <td>" . number_format($contract->total_price, 2) . "</td>
                        <td>100%</td>
                    </tr>
                    <tr>
                        <td>Enganche</td>
                        <td>" . number_format($contract->down_payment, 2) . "</td>
                        <td>" . round(($contract->down_payment / $contract->total_price) * 100, 2) . "%</td>
                    </tr>
                    <tr>
                        <td>Monto Financiado</td>
                        <td>" . number_format($contract->financing_amount, 2) . "</td>
                        <td>" . round(($contract->financing_amount / $contract->total_price) * 100, 2) . "%</td>
                    </tr>
                    <tr>
                        <td>Tasa de Interés Anual</td>
                        <td>" . ($contract->interest_rate * 100) . "%</td>
                        <td>-</td>
                    </tr>
                    <tr>
                        <td>Plazo</td>
                        <td>{$contract->term_months} meses</td>
                        <td>-</td>
                    </tr>
                    <tr class='total-row'>
                        <td>Pago Mensual</td>
                        <td>" . number_format($contract->monthly_payment, 2) . "</td>
                        <td>-</td>
                    </tr>
                    <tr class='total-row'>
                        <td>Total a Pagar</td>
                        <td>" . number_format($contract->down_payment + ($contract->monthly_payment * $contract->term_months), 2) . "</td>
                        <td>-</td>
                    </tr>
                </table>
            </div>

            <div class='section'>
                <h3>ESTADO DEL CONTRATO</h3>
                <p><strong>Estado:</strong> " . ucfirst($contract->status) . "</p>
            </div>

            <div class='section' style='margin-top: 50px;'>
                <p>Este es un documento de vista previa. El contrato final será generado una vez aprobado.</p>
            </div>
        </body>
        </html>
        ";
    }

    protected function finalHtml(Contract $contract): string
    {
        // Similar al preview pero con más detalles legales y términos completos
        $previewHtml = $this->previewHtml($contract);

        // Agregar términos y condiciones legales
        $legalTerms = "
            <div class='section'>
                <h3>TÉRMINOS Y CONDICIONES</h3>
                <p>1. El presente contrato se rige por las leyes vigentes.</p>
                <p>2. Los pagos deberán realizarse puntualmente según el cronograma establecido.</p>
                <p>3. En caso de incumplimiento, se aplicarán las penalidades correspondientes.</p>
                <!-- Agregar más términos según sea necesario -->
            </div>
            
            <div class='section' style='margin-top: 50px;'>
                <table style='width: 100%;'>
                    <tr>
                        <td style='text-align: center; padding: 20px;'>
                            <div style='border-top: 1px solid #000; width: 200px; margin: 0 auto;'>
                                <p>Firma del Cliente</p>
                            </div>
                        </td>
                        <td style='text-align: center; padding: 20px;'>
                            <div style='border-top: 1px solid #000; width: 200px; margin: 0 auto;'>
                                <p>Firma del Asesor</p>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        ";

        return str_replace('</body>', $legalTerms . '</body>', $previewHtml);
    }

    public function preview(Contract $contract): string
    {
        $path = 'contracts/previews/' . $contract->contract_id . '.pdf';

        // Asegurar que el directorio existe
        Storage::makeDirectory('contracts/previews');

        Browsershot::html($this->previewHtml($contract))
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->savePdf(Storage::path($path));

        return Storage::path($path);
    }

    public function finalize(Contract $contract): string
    {
        $path = 'contracts/' . $contract->contract_id . '.pdf';

        // Asegurar que el directorio existe
        Storage::makeDirectory('contracts');

        Browsershot::html($this->finalHtml($contract))
            ->format('A4')
            ->margins(10, 10, 10, 10)
            ->savePdf(Storage::path($path));

        // Actualizar el campo pdf_path en el contrato
        $contract->update(['pdf_path' => $path]);

        return Storage::path($path);
    }
}
