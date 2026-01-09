<?php

namespace App\Exports;

use App\Models\SalesCut;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SalesCutExport
{
    protected SalesCut $cut;

    public function __construct(SalesCut $cut)
    {
        $this->cut = $cut;
    }

    public function export(): string
    {
        $spreadsheet = new Spreadsheet();
        
        // Crear hojas
        $this->createSummarySheet($spreadsheet);
        $this->createItemsSheet($spreadsheet);
        $this->createAdvisorsSheet($spreadsheet);
        
        // Guardar archivo temporal
        $fileName = 'corte_' . $this->cut->cut_date->format('Y-m-d') . '_' . uniqid() . '.xlsx';
        $filePath = storage_path('app/temp/' . $fileName);
        
        // Crear directorio si no existe
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        
        return $filePath;
    }

    protected function createSummarySheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');
        
        // Headers style
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];
        
        // Title
        $sheet->setCellValue('A1', 'CORTE DE VENTAS - ' . strtoupper($this->cut->cut_type));
        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        
        // Date and Status
        $sheet->setCellValue('A2', 'Fecha: ' . $this->cut->cut_date->format('d/m/Y'));
        $sheet->setCellValue('C2', 'Estado: ' . strtoupper($this->cut->status));
        $sheet->mergeCells('A2:B2');
        $sheet->mergeCells('C2:D2');
        
        // Empty row
        $row = 4;
        
        // Metrics
        $metrics = [
            ['VENTAS DEL DÍA', ''],
            ['Total de Ventas', $this->cut->total_sales_count],
            ['Ingresos Totales', 'S/ ' . number_format($this->cut->total_revenue, 2)],
            ['Inicial Total', 'S/ ' . number_format($this->cut->total_down_payments, 2)],
            ['', ''],
            ['PAGOS RECIBIDOS', ''],
            ['Cantidad de Pagos', $this->cut->total_payments_count],
            ['Monto Recibido', 'S/ ' . number_format($this->cut->total_payments_received, 2)],
            ['Cuotas Pagadas', $this->cut->paid_installments_count],
            ['', ''],
            ['COMISIONES', ''],
            ['Total Comisiones', 'S/ ' . number_format($this->cut->total_commissions, 2)],
            ['', ''],
            ['BALANCE', ''],
            ['Efectivo', 'S/ ' . number_format($this->cut->cash_balance ?? 0, 2)],
            ['Banco', 'S/ ' . number_format($this->cut->bank_balance ?? 0, 2)],
            ['TOTAL', 'S/ ' . number_format(($this->cut->cash_balance ?? 0) + ($this->cut->bank_balance ?? 0), 2)],
        ];
        
        foreach ($metrics as $metric) {
            $sheet->setCellValue('A' . $row, $metric[0]);
            $sheet->setCellValue('B' . $row, $metric[1]);
            
            // Style for section headers
            if (empty($metric[1]) && !empty($metric[0])) {
                $sheet->getStyle('A' . $row)->applyFromArray($headerStyle);
                $sheet->mergeCells('A' . $row . ':B' . $row);
            }
            
            // Style for total
            if ($metric[0] === 'TOTAL') {
                $sheet->getStyle('A' . $row . ':B' . $row)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
                ]);
            }
            
            $row++;
        }
        
        // Column widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
        
        // Borders
        $sheet->getStyle('A4:B' . ($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
            ],
        ]);
        
        // Closed by info
        if ($this->cut->status === 'closed' && $this->cut->closedBy) {
            $row += 2;
            $sheet->setCellValue('A' . $row, 'Cerrado por: ' . $this->cut->closedBy->first_name . ' ' . $this->cut->closedBy->last_name);
            $sheet->setCellValue('A' . ($row + 1), 'Fecha de cierre: ' . $this->cut->closed_at->format('d/m/Y H:i'));
        }
    }

    protected function createItemsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detalle');
        
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        
        // Headers
        $headers = ['Tipo', 'Contrato', 'Cliente', 'Asesor', 'Monto', 'Método Pago', 'Comisión'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
        
        // Data
        $row = 2;
        foreach ($this->cut->items as $item) {
            $sheet->setCellValue('A' . $row, strtoupper($item->item_type));
            $sheet->setCellValue('B' . $row, $item->contract ? $item->contract->contract_number : '-');
            $sheet->setCellValue('C' . $row, $item->contract && $item->contract->client ? 
                $item->contract->client->first_name . ' ' . $item->contract->client->last_name : '-');
            $sheet->setCellValue('D' . $row, $item->contract && $item->contract->advisor ? 
                $item->contract->advisor->first_name . ' ' . $item->contract->advisor->last_name : '-');
            $sheet->setCellValue('E' . $row, 'S/ ' . number_format($item->amount, 2));
            $sheet->setCellValue('F' . $row, $item->payment_method ? strtoupper($item->payment_method) : '-');
            $sheet->setCellValue('G' . $row, $item->commission ? 'S/ ' . number_format($item->commission, 2) : '-');
            $row++;
        }
        
        // Column widths
        $sheet->getColumnDimension('A')->setWidth(12);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(25);
        $sheet->getColumnDimension('D')->setWidth(25);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);
        
        // Borders
        $sheet->getStyle('A1:G' . ($row - 1))->applyFromArray([
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
            ],
        ]);
        
        // Totals
        $row++;
        $sheet->setCellValue('D' . $row, 'TOTALES:');
        $sheet->setCellValue('E' . $row, 'S/ ' . number_format($this->cut->items->sum('amount'), 2));
        $sheet->setCellValue('G' . $row, 'S/ ' . number_format($this->cut->items->sum('commission'), 2));
        $sheet->getStyle('D' . $row . ':G' . $row)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
        ]);
    }

    protected function createAdvisorsSheet(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Asesores');
        
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];
        
        // Headers
        $headers = ['Asesor', 'Ventas', 'Monto Total', 'Comisión'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        
        // Data
        $advisors = $this->cut->summary_data['sales_by_advisor'] ?? [];
        $row = 2;
        foreach ($advisors as $advisor) {
            $sheet->setCellValue('A' . $row, $advisor['advisor_name']);
            $sheet->setCellValue('B' . $row, $advisor['sales_count']);
            $sheet->setCellValue('C' . $row, 'S/ ' . number_format($advisor['total_amount'], 2));
            $sheet->setCellValue('D' . $row, 'S/ ' . number_format($advisor['total_commission'], 2));
            $row++;
        }
        
        // Column widths
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(18);
        
        // Borders
        if ($row > 2) {
            $sheet->getStyle('A1:D' . ($row - 1))->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                ],
            ]);
            
            // Totals
            $row++;
            $sheet->setCellValue('A' . $row, 'TOTALES:');
            $sheet->setCellValue('B' . $row, collect($advisors)->sum('sales_count'));
            $sheet->setCellValue('C' . $row, 'S/ ' . number_format(collect($advisors)->sum('total_amount'), 2));
            $sheet->setCellValue('D' . $row, 'S/ ' . number_format(collect($advisors)->sum('total_commission'), 2));
            $sheet->getStyle('A' . $row . ':D' . $row)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
            ]);
        }
    }
}
