<?php

namespace Modules\Sales\Services;

use Carbon\Carbon;
use Modules\Sales\Models\Contract;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

class ContractExportService
{
    // ── Colores corporativos ─────────────────────────────────
    private const COLOR_PRIMARY     = 'FF1F4E79';   // Azul oscuro
    private const COLOR_SECONDARY   = 'FF2E75B6';   // Azul medio
    private const COLOR_HEADER_FONT = 'FFFFFFFF';   // Blanco
    private const COLOR_ROW_EVEN    = 'FFF2F7FB';   // Azul muy claro
    private const COLOR_ROW_ODD     = 'FFFFFFFF';   // Blanco
    private const COLOR_TOTALS_BG   = 'FFDCE6F1';   // Azul claro para totales
    private const COLOR_GREEN       = 'FF27AE60';   // Verde (vigente/pagado)
    private const COLOR_YELLOW      = 'FFF39C12';   // Amarillo (pendiente)
    private const COLOR_RED         = 'FFE74C3C';   // Rojo (vencido/rescindido)
    private const COLOR_ORANGE      = 'FFE67E22';   // Naranja (por aprobar)
    private const COLOR_GRAY        = 'FF95A5A6';   // Gris (cancelado)
    private const COLOR_BORDER      = 'FFBFBFBF';   // Gris bordes

    /**
     * Genera un archivo Excel profesional con el reporte de contratos.
     * Incluye: título, filtros, resumen, formato condicional, totales.
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

        // ── Propiedades del documento ────────────────────────────
        $spreadsheet->getProperties()
            ->setCreator('Casa Bonita')
            ->setTitle('Reporte de Contratos')
            ->setSubject('Contratos de Venta')
            ->setDescription('Reporte generado automáticamente por el sistema Casa Bonita')
            ->setCompany('Casa Bonita');

        // ── Hoja 1: Detalle de Contratos ─────────────────────────
        $this->createContractsSheet($spreadsheet, $contracts);

        // ── Hoja 2: Resumen ──────────────────────────────────────
        $this->createSummarySheet($spreadsheet, $contracts);

        // Activar la hoja de contratos como la primera visible
        $spreadsheet->setActiveSheetIndex(0);

        // ── Guardar archivo temporal ─────────────────────────────
        $filename = 'reporte_contratos_' . date('Y-m-d_His') . '.xlsx';
        $tempPath = storage_path('app/exports/' . $filename);

        $dir = dirname($tempPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return $tempPath;
    }

    /**
     * Hoja principal con el detalle de todos los contratos.
     */
    private function createContractsSheet(Spreadsheet $spreadsheet, $contracts): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Contratos');

        // ── Título del reporte (filas 1-3) ───────────────────────
        $sheet->setCellValue('A1', 'CASA BONITA');
        $sheet->setCellValue('A2', 'REPORTE DE CONTRATOS');
        $sheet->setCellValue('A3', 'Generado: ' . Carbon::now()->format('d/m/Y H:i') . '  |  Total: ' . $contracts->count() . ' contratos');

        $sheet->mergeCells('A1:V1');
        $sheet->mergeCells('A2:V2');
        $sheet->mergeCells('A3:V3');

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => self::COLOR_PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 13, 'color' => ['argb' => self::COLOR_SECONDARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['argb' => 'FF666666'], 'italic' => true],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(22);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // ── Headers (fila 4) ─────────────────────────────────────
        $headerRow = 4;
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
            'Precio Base (S/)',
            'Precio Total (S/)',
            'Descuento (S/)',
            'Cuota Inicial (S/)',
            'Monto Financiado (S/)',
            'Plazo (meses)',
            'Cuota Mensual (S/)',
            'Total Cuotas',
            'Cuotas Pagadas',
            'Cuotas Pendientes',
            'Cuotas Vencidas',
            'Estado Contrato',
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $headerRow, $header);
            $col++;
        }

        $lastCol = chr(ord('A') + count($headers) - 1); // 'V'
        $headerRange = "A{$headerRow}:{$lastCol}{$headerRow}";

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['argb' => self::COLOR_HEADER_FONT],
                'size' => 10,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => self::COLOR_PRIMARY],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => self::COLOR_BORDER],
                ],
            ],
        ]);

        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        // ── AutoFiltro en los headers ────────────────────────────
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");

        // ── Data (desde fila 5) ──────────────────────────────────
        $dataStartRow = $headerRow + 1;
        $row = $dataStartRow;

        foreach ($contracts as $contract) {
            $client  = $contract->getClient();
            $lot     = $contract->getLot();
            $advisor = $contract->getAdvisor();
            $schedules = $contract->paymentSchedules;

            $totalSchedules   = $schedules->count();
            $paidSchedules    = $schedules->where('status', 'pagado')->count();
            $overdueSchedules = $schedules->where('status', 'vencido')->count();
            $pendingSchedules = $schedules->where('status', 'pendiente')->count();

            $saleType = match ($contract->sale_type) {
                'cash'     => 'Contado',
                'financed' => 'Financiado',
                default    => $contract->sale_type ?? '-',
            };

            $contractStatus = $this->translateContractStatus($contract->status);
            $lotStatus      = $this->translateLotStatus($lot->status ?? null);

            $data = [
                $contract->contract_number ?? '-',
                $contract->getClientName() ?? '-',
                $client->doc_number ?? '-',
                $contract->getManzanaName() ?? '-',
                $contract->getLotName() ?? '-',
                $lot->area_m2 ?? 0,
                $lotStatus,
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
                $contractStatus,
            ];

            $col = 'A';
            foreach ($data as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }

            // Color del estado del contrato (columna V)
            $statusColor = $this->getStatusColor($contract->status);
            if ($statusColor) {
                $sheet->getStyle("V{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 9],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $statusColor]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            // Color del estado del lote (columna G)
            $lotStatusColor = $this->getLotStatusColor($lot->status ?? null);
            if ($lotStatusColor) {
                $sheet->getStyle("G{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 9],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $lotStatusColor]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            // Resaltar cuotas vencidas en rojo si hay alguna
            if ($overdueSchedules > 0) {
                $sheet->getStyle("U{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => self::COLOR_RED]],
                ]);
            }

            $row++;
        }

        // ── Estilos de datos ─────────────────────────────────────
        $lastRow = $row - 1;
        if ($lastRow >= $dataStartRow) {
            // Bordes para todo el rango de datos
            $sheet->getStyle("A{$dataStartRow}:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => self::COLOR_BORDER],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'font' => ['size' => 10],
            ]);

            // Formato numérico para columnas de montos (S/)
            $moneyColumns = ['K', 'L', 'M', 'N', 'O', 'Q'];
            foreach ($moneyColumns as $mc) {
                $sheet->getStyle("{$mc}{$dataStartRow}:{$mc}{$lastRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"S/" #,##0.00');
            }

            // Formato numérico para área
            $sheet->getStyle("F{$dataStartRow}:F{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode('#,##0.00');

            // Centrar columnas específicas
            $centerCols = ['A', 'D', 'E', 'F', 'G', 'I', 'J', 'P', 'R', 'S', 'T', 'U', 'V'];
            foreach ($centerCols as $cc) {
                $sheet->getStyle("{$cc}{$dataStartRow}:{$cc}{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Alinear montos a la derecha
            foreach ($moneyColumns as $mc) {
                $sheet->getStyle("{$mc}{$dataStartRow}:{$mc}{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            // Filas alternadas con color de fondo sutil
            for ($r = $dataStartRow; $r <= $lastRow; $r++) {
                $bgColor = ($r % 2 === 0) ? self::COLOR_ROW_EVEN : self::COLOR_ROW_ODD;
                // Aplicar solo a celdas que no tienen color de estado
                foreach (range('A', 'F') as $c) {
                    $sheet->getStyle("{$c}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                    ]);
                }
                // Saltar G (estado lote) y continuar H-U
                foreach (['H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U'] as $c) {
                    $sheet->getStyle("{$c}{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bgColor]],
                    ]);
                }
                // Saltar V (estado contrato, ya tiene color propio)
            }

            // ── Fila de TOTALES ──────────────────────────────────
            $totalsRow = $lastRow + 1;
            $sheet->setCellValue("A{$totalsRow}", 'TOTALES');
            $sheet->mergeCells("A{$totalsRow}:J{$totalsRow}");

            // Fórmulas SUBTOTAL (respetan filtros activos)
            $sheet->setCellValue("K{$totalsRow}", "=SUBTOTAL(9,K{$dataStartRow}:K{$lastRow})");
            $sheet->setCellValue("L{$totalsRow}", "=SUBTOTAL(9,L{$dataStartRow}:L{$lastRow})");
            $sheet->setCellValue("M{$totalsRow}", "=SUBTOTAL(9,M{$dataStartRow}:M{$lastRow})");
            $sheet->setCellValue("N{$totalsRow}", "=SUBTOTAL(9,N{$dataStartRow}:N{$lastRow})");
            $sheet->setCellValue("O{$totalsRow}", "=SUBTOTAL(9,O{$dataStartRow}:O{$lastRow})");
            $sheet->setCellValue("Q{$totalsRow}", "=SUBTOTAL(9,Q{$dataStartRow}:Q{$lastRow})");
            $sheet->setCellValue("R{$totalsRow}", "=SUBTOTAL(9,R{$dataStartRow}:R{$lastRow})");
            $sheet->setCellValue("S{$totalsRow}", "=SUBTOTAL(9,S{$dataStartRow}:S{$lastRow})");
            $sheet->setCellValue("T{$totalsRow}", "=SUBTOTAL(9,T{$dataStartRow}:T{$lastRow})");
            $sheet->setCellValue("U{$totalsRow}", "=SUBTOTAL(9,U{$dataStartRow}:U{$lastRow})");

            $sheet->getStyle("A{$totalsRow}:{$lastCol}{$totalsRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => self::COLOR_TOTALS_BG],
                ],
                'borders' => [
                    'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::COLOR_PRIMARY]],
                    'bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::COLOR_PRIMARY]],
                ],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);

            // Formato moneda en totales
            foreach ($moneyColumns as $mc) {
                $sheet->getStyle("{$mc}{$totalsRow}")
                    ->getNumberFormat()
                    ->setFormatCode('"S/" #,##0.00');
                $sheet->getStyle("{$mc}{$totalsRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }

            // Centrar conteos en totales
            foreach (['R', 'S', 'T', 'U'] as $cc) {
                $sheet->getStyle("{$cc}{$totalsRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            // Actualizar el rango del autofiltro para incluir datos
            $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastRow}");
        }

        // ── Anchos de columna optimizados ────────────────────────
        $columnWidths = [
            'A' => 16,  // N° Contrato
            'B' => 28,  // Cliente
            'C' => 14,  // DNI/RUC
            'D' => 12,  // Manzana
            'E' => 10,  // Lote
            'F' => 12,  // Área
            'G' => 14,  // Estado Lote
            'H' => 25,  // Asesor
            'I' => 15,  // Fecha
            'J' => 14,  // Tipo Venta
            'K' => 16,  // Precio Base
            'L' => 16,  // Precio Total
            'M' => 14,  // Descuento
            'N' => 16,  // Cuota Inicial
            'O' => 18,  // Monto Financiado
            'P' => 12,  // Plazo
            'Q' => 16,  // Cuota Mensual
            'R' => 12,  // Total Cuotas
            'S' => 14,  // Cuotas Pagadas
            'T' => 16,  // Cuotas Pendientes
            'U' => 16,  // Cuotas Vencidas
            'V' => 18,  // Estado
        ];

        foreach ($columnWidths as $c => $w) {
            $sheet->getColumnDimension($c)->setWidth($w);
        }

        // ── Congelar encabezado y título ─────────────────────────
        $sheet->freezePane('A' . ($headerRow + 1));

        // ── Configuración de impresión ───────────────────────────
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->getHeaderFooter()->setOddHeader('&L&B' . 'CASA BONITA - Reporte de Contratos' . '&R&D');
        $sheet->getHeaderFooter()->setOddFooter('&LGenerado el ' . date('d/m/Y H:i') . '&RPágina &P de &N');

        $sheet->getPageMargins()->setTop(0.5);
        $sheet->getPageMargins()->setBottom(0.5);
        $sheet->getPageMargins()->setLeft(0.3);
        $sheet->getPageMargins()->setRight(0.3);
    }

    /**
     * Hoja de resumen con estadísticas y totales agrupados.
     */
    private function createSummarySheet(Spreadsheet $spreadsheet, $contracts): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Resumen');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['argb' => self::COLOR_HEADER_FONT], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::COLOR_PRIMARY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::COLOR_BORDER]]],
        ];

        $sectionStyle = [
            'font' => ['bold' => true, 'size' => 11, 'color' => ['argb' => self::COLOR_PRIMARY]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE8EEF4']],
            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => self::COLOR_PRIMARY]]],
        ];

        // ── Título ───────────────────────────────────────────────
        $sheet->setCellValue('A1', 'RESUMEN DE CONTRATOS');
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['argb' => self::COLOR_PRIMARY]],
        ]);
        $sheet->setCellValue('A2', 'Fecha de generación: ' . Carbon::now()->format('d/m/Y H:i'));
        $sheet->mergeCells('A2:D2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'color' => ['argb' => 'FF666666']],
        ]);

        $row = 4;

        // ── Sección 1: RESUMEN GENERAL ───────────────────────────
        $sheet->setCellValue("A{$row}", '📊 RESUMEN GENERAL');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($sectionStyle);
        $row++;

        $totalContracts = $contracts->count();
        $totalRevenue   = $contracts->sum('total_price');
        $totalDown      = $contracts->sum('down_payment');
        $totalFinanced  = $contracts->sum('financing_amount');
        $totalDiscount  = $contracts->sum('discount');

        $generalStats = [
            ['Total de Contratos', $totalContracts, '', ''],
            ['Ingresos Totales (Precio)', 'S/ ' . number_format($totalRevenue, 2), '', ''],
            ['Total Cuotas Iniciales', 'S/ ' . number_format($totalDown, 2), '', ''],
            ['Total Financiado', 'S/ ' . number_format($totalFinanced, 2), '', ''],
            ['Total Descuentos', 'S/ ' . number_format($totalDiscount, 2), '', ''],
        ];

        foreach ($generalStats as $stat) {
            $sheet->setCellValue("A{$row}", $stat[0]);
            $sheet->setCellValue("B{$row}", $stat[1]);
            $sheet->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true]]);
            $sheet->getStyle("B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => self::COLOR_SECONDARY]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ]);
            $row++;
        }

        $row += 1;

        // ── Sección 2: POR ESTADO DE CONTRATO ────────────────────
        $sheet->setCellValue("A{$row}", '📋 POR ESTADO DE CONTRATO');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($sectionStyle);
        $row++;

        $headers2 = ['Estado', 'Cantidad', '% del Total', 'Monto Total'];
        $col = 'A';
        foreach ($headers2 as $h) {
            $sheet->setCellValue("{$col}{$row}", $h);
            $col++;
        }
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($headerStyle);
        $row++;

        $statusGroups = $contracts->groupBy('status');
        foreach ($statusGroups as $status => $group) {
            $count   = $group->count();
            $percent = $totalContracts > 0 ? round(($count / $totalContracts) * 100, 1) : 0;
            $amount  = $group->sum('total_price');

            $sheet->setCellValue("A{$row}", $this->translateContractStatus($status));
            $sheet->setCellValue("B{$row}", $count);
            $sheet->setCellValue("C{$row}", $percent . '%');
            $sheet->setCellValue("D{$row}", 'S/ ' . number_format($amount, 2));

            $statusColor = $this->getStatusColor($status);
            if ($statusColor) {
                $sheet->getStyle("A{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $statusColor]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
            }

            $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $row++;
        }

        $row += 1;

        // ── Sección 3: POR TIPO DE VENTA ─────────────────────────
        $sheet->setCellValue("A{$row}", '💰 POR TIPO DE VENTA');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($sectionStyle);
        $row++;

        $col = 'A';
        foreach (['Tipo', 'Cantidad', '% del Total', 'Monto Total'] as $h) {
            $sheet->setCellValue("{$col}{$row}", $h);
            $col++;
        }
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($headerStyle);
        $row++;

        $saleTypeGroups = $contracts->groupBy('sale_type');
        foreach ($saleTypeGroups as $type => $group) {
            $count   = $group->count();
            $percent = $totalContracts > 0 ? round(($count / $totalContracts) * 100, 1) : 0;
            $amount  = $group->sum('total_price');
            $label   = match ($type) {
                'cash'     => 'Contado',
                'financed' => 'Financiado',
                default    => $type ?? 'Sin definir',
            };

            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $count);
            $sheet->setCellValue("C{$row}", $percent . '%');
            $sheet->setCellValue("D{$row}", 'S/ ' . number_format($amount, 2));

            $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $row++;
        }

        $row += 1;

        // ── Sección 4: POR ASESOR ────────────────────────────────
        $sheet->setCellValue("A{$row}", '👤 POR ASESOR');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($sectionStyle);
        $row++;

        $col = 'A';
        foreach (['Asesor', 'Contratos', '% del Total', 'Monto Total'] as $h) {
            $sheet->setCellValue("{$col}{$row}", $h);
            $col++;
        }
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($headerStyle);
        $row++;

        // Agrupar por asesor
        $advisorData = [];
        foreach ($contracts as $contract) {
            $advisor = $contract->getAdvisor();
            $name = $advisor ? trim(($advisor->first_name ?? '') . ' ' . ($advisor->last_name ?? '')) : 'Sin asesor';
            if (!isset($advisorData[$name])) {
                $advisorData[$name] = ['count' => 0, 'amount' => 0];
            }
            $advisorData[$name]['count']++;
            $advisorData[$name]['amount'] += ($contract->total_price ?? 0);
        }

        // Ordenar por monto descendente
        uasort($advisorData, fn($a, $b) => $b['amount'] <=> $a['amount']);

        foreach ($advisorData as $name => $data) {
            $percent = $totalContracts > 0 ? round(($data['count'] / $totalContracts) * 100, 1) : 0;

            $sheet->setCellValue("A{$row}", $name);
            $sheet->setCellValue("B{$row}", $data['count']);
            $sheet->setCellValue("C{$row}", $percent . '%');
            $sheet->setCellValue("D{$row}", 'S/ ' . number_format($data['amount'], 2));

            $sheet->getStyle("B{$row}:C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $row++;
        }

        $row += 1;

        // ── Sección 5: ESTADO DE COBRANZA ────────────────────────
        $sheet->setCellValue("A{$row}", '📅 ESTADO DE COBRANZA');
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->getStyle("A{$row}:D{$row}")->applyFromArray($sectionStyle);
        $row++;

        $totalSchedules = 0;
        $totalPaid = 0;
        $totalPending = 0;
        $totalOverdue = 0;

        foreach ($contracts as $contract) {
            $schedules = $contract->paymentSchedules;
            $totalSchedules += $schedules->count();
            $totalPaid      += $schedules->where('status', 'pagado')->count();
            $totalPending   += $schedules->where('status', 'pendiente')->count();
            $totalOverdue   += $schedules->where('status', 'vencido')->count();
        }

        $collectionStats = [
            ['Total de Cuotas', $totalSchedules],
            ['Cuotas Pagadas', $totalPaid],
            ['Cuotas Pendientes', $totalPending],
            ['Cuotas Vencidas', $totalOverdue],
            ['% de Cobro', $totalSchedules > 0 ? round(($totalPaid / $totalSchedules) * 100, 1) . '%' : '0%'],
        ];

        foreach ($collectionStats as $stat) {
            $sheet->setCellValue("A{$row}", $stat[0]);
            $sheet->setCellValue("B{$row}", $stat[1]);
            $sheet->getStyle("A{$row}")->applyFromArray(['font' => ['bold' => true]]);
            $sheet->getStyle("B{$row}")->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Color para vencidas
            if ($stat[0] === 'Cuotas Vencidas' && $totalOverdue > 0) {
                $sheet->getStyle("B{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => self::COLOR_RED]],
                ]);
            }
            // Color para pagadas
            if ($stat[0] === 'Cuotas Pagadas') {
                $sheet->getStyle("B{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => self::COLOR_GREEN]],
                ]);
            }

            $row++;
        }

        // ── Bordes generales ─────────────────────────────────────
        $sheet->getStyle("A4:D{$row}")->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD9D9D9']],
            ],
        ]);

        // ── Anchos de columna ────────────────────────────────────
        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(14);
        $sheet->getColumnDimension('D')->setWidth(20);

        // ── Configuración de impresión ───────────────────────────
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_PORTRAIT)
            ->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function translateContractStatus(?string $status): string
    {
        return match ($status) {
            'vigente'               => 'Vigente',
            'pendiente_aprobacion'  => 'Pendiente Aprobación',
            'aprobado'              => 'Aprobado',
            'rescindido'            => 'Rescindido',
            'cancelado'             => 'Cancelado',
            'completado'            => 'Completado',
            'vencido'               => 'Vencido',
            default                 => $status ?? '-',
        };
    }

    private function translateLotStatus(?string $status): string
    {
        return match ($status) {
            'disponible' => 'Disponible',
            'reservado'  => 'Reservado',
            'vendido'    => 'Vendido',
            'bloqueado'  => 'Bloqueado',
            default      => $status ?? '-',
        };
    }

    private function getStatusColor(?string $status): ?string
    {
        return match ($status) {
            'vigente', 'completado', 'aprobado' => self::COLOR_GREEN,
            'pendiente_aprobacion'               => self::COLOR_ORANGE,
            'rescindido', 'vencido'              => self::COLOR_RED,
            'cancelado'                          => self::COLOR_GRAY,
            default                              => null,
        };
    }

    private function getLotStatusColor(?string $status): ?string
    {
        return match ($status) {
            'disponible' => self::COLOR_GREEN,
            'reservado'  => self::COLOR_YELLOW,
            'vendido'    => self::COLOR_SECONDARY,
            'bloqueado'  => self::COLOR_GRAY,
            default      => null,
        };
    }
}
