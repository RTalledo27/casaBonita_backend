<?php

namespace Modules\Sales\Services;

use Carbon\Carbon;
use Modules\Sales\Models\Contract;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ContractExportService
{
    /**
     * Genera un archivo Excel con el reporte de contratos.
     * Retorna la ruta completa del archivo temporal generado.
     */
    public function generate(): string
    {
        $contracts = Contract::with([
            'client',
            'lot.manzana',
            'advisor',
            'paymentSchedules',
            'reservation.client',
            'reservation.lot.manzana',
            'reservation.advisor',
        ])->orderBy('contract_id', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Contratos');

        // ── Headers ──────────────────────────────────────────────
        $headers = [
            'N° Contrato',
            'Cliente',
            'DNI/RUC',
            'Manzana',
            'Lote',
            'Área (m²)',
            'Estado Lote',
            'Asesor',
            'Fecha de Venta',
            'Tipo de Venta',
            'Precio Base',
            'Precio Total',
            'Descuento',
            'Cuota Inicial',
            'Monto Financiado',
            'Plazo (meses)',
            'Cuota Mensual',
            'Total Cuotas',
            'Cuotas Pagadas',
            'Cuotas Pendientes',
            'Cuotas Vencidas',
            'Estado Contrato',
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }

        // Estilo del header
        $lastCol = chr(ord('A') + count($headers) - 1); // 'V'
        $headerRange = "A1:{$lastCol}1";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF1F4E79'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFCCCCCC'],
                ],
            ],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);

        // ── Data ─────────────────────────────────────────────────
        $row = 2;

        foreach ($contracts as $contract) {
            $client = $contract->getClient();
            $lot    = $contract->getLot();
            $advisor = $contract->getAdvisor();
            $schedules = $contract->paymentSchedules;

            $totalSchedules  = $schedules->count();
            $paidSchedules   = $schedules->where('status', 'pagado')->count();
            $overdueSchedules = $schedules->where('status', 'vencido')->count();
            $pendingSchedules = $schedules->where('status', 'pendiente')->count();

            $saleType = match($contract->sale_type) {
                'cash' => 'Contado',
                'financed' => 'Financiado',
                default => $contract->sale_type ?? '-',
            };

            $data = [
                $contract->contract_number ?? '-',
                $contract->getClientName() ?? '-',
                $client->doc_number ?? '-',
                $contract->getManzanaName() ?? '-',
                $contract->getLotName() ?? '-',
                $lot->area_m2 ?? '-',
                $lot->status ?? '-',
                $advisor ? trim(($advisor->first_name ?? '') . ' ' . ($advisor->last_name ?? '')) : '-',
                $contract->contract_date ? Carbon::parse($contract->contract_date)->format('d/m/Y') : '-',
                $saleType,
                $contract->base_price ?? 0,
                $contract->total_price ?? 0,
                $contract->discount ?? 0,
                $contract->down_payment ?? 0,
                $contract->financing_amount ?? 0,
                $contract->term_months ?? 0,
                $contract->monthly_payment ?? 0,
                $totalSchedules,
                $paidSchedules,
                $pendingSchedules,
                $overdueSchedules,
                $contract->status ?? '-',
            ];

            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        // ── Estilos de datos ─────────────────────────────────────
        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            // Bordes para todo el rango de datos
            $sheet->getStyle("A2:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FFDDDDDD'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Formato numérico para columnas de precios (K-Q = 11-17)
            $moneyColumns = ['K', 'L', 'M', 'N', 'O', 'Q'];
            foreach ($moneyColumns as $mc) {
                $sheet->getStyle("{$mc}2:{$mc}{$lastRow}")->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }

            // Filas alternadas con color de fondo sutil
            for ($r = 2; $r <= $lastRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['argb' => 'FFF2F7FB'],
                        ],
                    ]);
                }
            }
        }

        // ── Auto-size columnas ───────────────────────────────────
        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // ── Congelar primera fila ────────────────────────────────
        $sheet->freezePane('A2');

        // ── Guardar archivo temporal ─────────────────────────────
        $filename = 'reporte_contratos_' . date('Y-m-d_His') . '.xlsx';
        $tempPath = storage_path('app/exports/' . $filename);

        // Asegurar que el directorio exista
        $dir = dirname($tempPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return $tempPath;
    }
}
