<?php

namespace Modules\Reports\Services;

use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Reports\Repositories\PaymentsRepository;
use Illuminate\Support\Facades\App;

class ExportService
{
    /**
     * Generate report based on type and export to specified format
     */
    public function generateReport($type, $format, $filters = [], $dateFrom = null, $dateTo = null, $userId = null)
    {
        // Get data based on report type
        $data = $this->getReportData($type, $filters, $dateFrom, $dateTo);
        
        // Generate filename
        $filename = $type . '_report_' . date('YmdHis');
        
        // Get headers based on report type
        $headers = $this->getReportHeaders($type);
        
        // Export to specified format
        return $this->export($data, $format, $filename, $headers);
    }

    /**
     * Get report data based on type
     */
    private function getReportData($type, $filters = [], $dateFrom = null, $dateTo = null)
    {
        // This is a simplified version - you should implement specific logic for each report type
        // For now, return empty array or mock data
        
        switch ($type) {
            case 'sales':
                return $this->getSalesReportData($filters, $dateFrom, $dateTo);
            case 'payment_schedules':
                return $this->getPaymentSchedulesData($filters, $dateFrom, $dateTo);
            case 'projections':
                return $this->getProjectionsData($filters);
            case 'collections':
                return $this->getCollectionsData($filters, $dateFrom, $dateTo);
            case 'inventory':
                return $this->getInventoryData($filters);
            default:
                return [];
        }
    }

    /**
     * Get headers based on report type
     */
    private function getReportHeaders($type)
    {
        $headers = [
            'sales' => ['Fecha', 'Cliente', 'Lote', 'Monto', 'Asesor', 'Estado'],
            'payment_schedules' => ['N° Cuota (ID)', 'Cliente', 'Correo', 'Lote', 'Fecha Vencimiento', 'Monto Cuota', 'Monto Venta', 'Estado'],
            'projections' => ['Mes', 'Ingresos Proyectados', 'Ventas Proyectadas', 'Confianza'],
            'collections' => ['Contrato', 'Cliente', 'Monto Adeudado', 'Días Vencidos', 'Estado'],
            'inventory' => ['Manzana', 'Lote', 'Estado', 'Precio', 'Área']
        ];

        return $headers[$type] ?? [];
    }

    /**
     * Get sales report data
     */
    private function getSalesReportData($filters, $dateFrom, $dateTo)
    {
        // Mock data for now - implement actual query
        return [
            ['2025-10-01', 'Juan Pérez', 'A-15', '$150,000', 'Luis Tavara', 'Vendido'],
            ['2025-10-02', 'María García', 'B-20', '$180,000', 'Renzo Castillo', 'Reservado']
        ];
    }

    /**
     * Get payment schedules data
     */
    private function getPaymentSchedulesData($filters, $dateFrom, $dateTo)
    {
        $repository = App::make(PaymentsRepository::class);
        $status = $filters['status'] ?? null;
        $clientId = $filters['clientId'] ?? null;
        
        $options = [
            'searchTerm' => $filters['searchTerm'] ?? null,
            'sortField' => $filters['sortField'] ?? null,
            'sortDirection' => $filters['sortDirection'] ?? null,
        ];

        // Use a large per_page to get all records for export, limit to 5000 for server safety
        $response = $repository->getByStatus($status, $dateFrom, $dateTo, $clientId, 1, 5000, $options);
        
        $exportData = [];
        foreach ($response['data'] as $item) {
            $exportData[] = [
                $item->schedule_id,
                $item->client_name ?? '-',
                $item->client_email ?? '-',
                $item->lot_number ?? '-',
                $item->due_date,
                '$' . number_format($item->amount, 2),
                $item->sale_amount ? '$' . number_format($item->sale_amount, 2) : '-',
                ucfirst($item->status)
            ];
        }
        
        return $exportData;
    }

    /**
     * Get projections data
     */
    private function getProjectionsData($filters)
    {
        return [];
    }

    /**
     * Get collections data
     */
    private function getCollectionsData($filters, $dateFrom, $dateTo)
    {
        return [];
    }

    /**
     * Get inventory data
     */
    private function getInventoryData($filters)
    {
        return [];
    }

    /**
     * Export data to specified format
     */
    public function export($data, $format, $filename, $headers = [])
    {
        switch (strtolower($format)) {
            case 'excel':
            case 'xlsx':
                return $this->exportToExcel($data, $filename, $headers);
            case 'csv':
                return $this->exportToCsv($data, $filename, $headers);
            case 'pdf':
                return $this->exportToPdf($data, $filename, $headers);
            default:
                throw new \InvalidArgumentException("Formato no soportado: {$format}");
        }
    }

    /**
     * Export to Excel format
     */
    private function exportToExcel($data, $filename, $headers = [])
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        if (!empty($headers)) {
            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $col++;
            }
        }

        // Set data
        $row = 2;
        foreach ($data as $item) {
            $col = 'A';
            foreach ($item as $value) {
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $filePath = 'exports/' . $filename . '.xlsx';
        
        // Save to public disk
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        
        Storage::disk('public')->put($filePath, $content);
        
        return [
            'file_path' => $filePath,
            'file_size' => strlen($content),
            'download_url' => Storage::disk('public')->url($filePath)
        ];
    }

    /**
     * Export to CSV format
     */
    private function exportToCsv($data, $filename, $headers = [])
    {
        $output = fopen('php://temp', 'w');
        
        // Write headers
        if (!empty($headers)) {
            fputcsv($output, $headers);
        }
        
        // Write data
        foreach ($data as $item) {
            fputcsv($output, $item);
        }
        
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        
        $filePath = 'exports/' . $filename . '.csv';
        Storage::disk('public')->put($filePath, $content);
        
        return [
            'file_path' => $filePath,
            'file_size' => strlen($content),
            'download_url' => Storage::disk('public')->url($filePath)
        ];
    }

    /**
     * Export to PDF format
     */
    private function exportToPdf($data, $filename, $headers = [])
    {
        $html = $this->generateHtmlTable($data, $headers);
        
        $pdf = Pdf::loadHTML($html);
        $content = $pdf->output();
        
        $filePath = 'exports/' . $filename . '.pdf';
        Storage::disk('public')->put($filePath, $content);
        
        return [
            'file_path' => $filePath,
            'file_size' => strlen($content),
            'download_url' => Storage::disk('public')->url($filePath)
        ];
    }

    /**
     * Generate HTML table for PDF export
     */
    private function generateHtmlTable($data, $headers = [])
    {
        $html = '<table border="1" cellpadding="5" cellspacing="0">';
        
        // Headers
        if (!empty($headers)) {
            $html .= '<thead><tr>';
            foreach ($headers as $header) {
                $html .= '<th>' . htmlspecialchars($header) . '</th>';
            }
            $html .= '</tr></thead>';
        }
        
        // Data
        $html .= '<tbody>';
        foreach ($data as $item) {
            $html .= '<tr>';
            foreach ($item as $value) {
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        return $html;
    }

    /**
     * Get file info
     */
    public function getFileInfo($filePath)
    {
        if (!Storage::exists($filePath)) {
            return null;
        }

        return [
            'file_path' => $filePath,
            'file_size' => Storage::size($filePath),
            'download_url' => Storage::url($filePath),
            'created_at' => Carbon::createFromTimestamp(Storage::lastModified($filePath))
        ];
    }

    /**
     * Delete exported file
     */
    public function deleteFile($filePath)
    {
        return Storage::delete($filePath);
    }
}