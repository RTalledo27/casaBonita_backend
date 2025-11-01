<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Dompdf\Dompdf;
use Dompdf\Options;

class ExportService
{
    protected $reportsService;
    protected $salesReportsService;
    protected $paymentSchedulesService;
    protected $projectionsService;

    public function __construct(
        ReportsService $reportsService,
        SalesReportsService $salesReportsService,
        PaymentSchedulesService $paymentSchedulesService,
        ProjectionsService $projectionsService
    ) {
        $this->reportsService = $reportsService;
        $this->salesReportsService = $salesReportsService;
        $this->paymentSchedulesService = $paymentSchedulesService;
        $this->projectionsService = $projectionsService;
    }

    /**
     * Generate report in specified format
     */
    public function generateReport(string $reportType, string $format, array $filters, ?int $templateId = null): array
    {
        // Get data based on report type
        $data = $this->getReportData($reportType, $filters);
        
        // Get template configuration if provided
        $template = null;
        if ($templateId) {
            $template = $this->reportsService->getTemplateById($templateId);
        }

        // Generate filename
        $filename = $this->generateFilename($reportType, $format);
        
        // Log the report generation
        $reportLogId = $this->reportsService->logGeneratedReport([
            'template_id' => $templateId,
            'user_id' => auth()->id() ?? 1, // Default to admin user if not authenticated
            'file_name' => $filename,
            'file_path' => 'reports/' . $filename,
            'format' => $format,
            'parameters' => $filters,
            'status' => 'generating'
        ]);

        try {
            // Generate file based on format
            $filePath = $this->generateFile($data, $format, $filename, $template);
            
            // Update report status to completed
            $this->reportsService->updateGeneratedReportStatus($reportLogId, 'completed');
            
            return [
                'file_url' => Storage::url($filePath),
                'file_name' => $filename,
                'expires_at' => Carbon::now()->addDays(7)->toISOString(),
                'report_id' => $reportLogId
            ];
        } catch (\Exception $e) {
            // Update report status to failed
            $this->reportsService->updateGeneratedReportStatus($reportLogId, 'failed');
            throw $e;
        }
    }

    /**
     * Get report data based on type
     */
    protected function getReportData(string $reportType, array $filters): array
    {
        switch ($reportType) {
            case 'sales':
                return $this->salesReportsService->getSalesReport($filters, 1, 1000);
            case 'payments':
                return $this->paymentSchedulesService->getPaymentSchedules($filters, 1, 1000);
            case 'projections':
                return $this->projectionsService->getProjections(
                    $filters['projection_type'] ?? 'revenue',
                    $filters['period_months'] ?? 12,
                    $filters['base_date'] ?? now()->format('Y-m-d'),
                    $filters['office_id'] ?? null
                );
            default:
                throw new \Exception('Tipo de reporte no válido');
        }
    }

    /**
     * Generate file based on format
     */
    protected function generateFile(array $data, string $format, string $filename, ?array $template = null): string
    {
        switch ($format) {
            case 'excel':
                return $this->generateExcelFile($data, $filename, $template);
            case 'csv':
                return $this->generateCsvFile($data, $filename, $template);
            case 'pdf':
                return $this->generatePdfFile($data, $filename, $template);
            default:
                throw new \Exception('Formato de exportación no válido');
        }
    }

    /**
     * Export data to Excel with multiple sheets
     */
    public function exportToExcel(array $data, string $filename): string
    {
        $spreadsheet = new Spreadsheet();
        
        $sheetIndex = 0;
        foreach ($data as $sheetName => $sheetData) {
            if ($sheetIndex > 0) {
                $spreadsheet->createSheet();
            }
            
            $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
            $sheet->setTitle($sheetName);
            
            // Add data to sheet
            $row = 1;
            foreach ($sheetData as $rowData) {
                $col = 'A';
                foreach ($rowData as $cellValue) {
                    $sheet->setCellValue($col . $row, $cellValue);
                    $col++;
                }
                $row++;
            }
            
            // Style the header row
            if (!empty($sheetData)) {
                $sheet->getStyle('1:1')->getFont()->setBold(true);
                $sheet->getStyle('1:1')->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E2E8F0');
                
                // Auto-size columns
                foreach (range('A', $sheet->getHighestColumn()) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
            }
            
            $sheetIndex++;
        }
        
        $writer = new Xlsx($spreadsheet);
        $filePath = 'exports/' . $filename;
        $fullPath = storage_path('app/' . $filePath);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $writer->save($fullPath);
        
        return $filePath;
    }

    /**
     * Generate Excel file
     */
    protected function generateExcelFile(array $data, string $filename, ?array $template = null): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = $this->getHeaders($data, $template);
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }

        // Set data
        $row = 2;
        $dataArray = $data['data'] ?? $data['schedules'] ?? $data['projections'] ?? [];
        
        foreach ($dataArray as $item) {
            $col = 1;
            foreach ($headers as $key => $header) {
                $value = $this->getValueFromItem($item, $key);
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }

        // Style the header row
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E2E8F0');

        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filePath = 'reports/' . $filename;
        $fullPath = storage_path('app/public/' . $filePath);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $writer->save($fullPath);

        return $filePath;
    }

    /**
     * Generate CSV file
     */
    protected function generateCsvFile(array $data, string $filename, ?array $template = null): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $headers = $this->getHeaders($data, $template);
        $col = 1;
        foreach ($headers as $header) {
            $sheet->setCellValueByColumnAndRow($col, 1, $header);
            $col++;
        }

        // Set data
        $row = 2;
        $dataArray = $data['data'] ?? $data['schedules'] ?? $data['projections'] ?? [];
        
        foreach ($dataArray as $item) {
            $col = 1;
            foreach ($headers as $key => $header) {
                $value = $this->getValueFromItem($item, $key);
                $sheet->setCellValueByColumnAndRow($col, $row, $value);
                $col++;
            }
            $row++;
        }

        $writer = new Csv($spreadsheet);
        $filePath = 'reports/' . $filename;
        $fullPath = storage_path('app/public/' . $filePath);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        $writer->save($fullPath);

        return $filePath;
    }

    /**
     * Generate PDF file
     */
    protected function generatePdfFile(array $data, string $filename, ?array $template = null): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        $html = $this->generatePdfHtml($data, $template);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filePath = 'reports/' . $filename;
        $fullPath = storage_path('app/public/' . $filePath);
        
        // Ensure directory exists
        $directory = dirname($fullPath);
        if (!file_exists($directory)) {
            mkdir($directory, 0755, true);
        }
        
        file_put_contents($fullPath, $dompdf->output());

        return $filePath;
    }

    /**
     * Generate HTML for PDF
     */
    protected function generatePdfHtml(array $data, ?array $template = null): string
    {
        $headers = $this->getHeaders($data, $template);
        $dataArray = $data['data'] ?? $data['schedules'] ?? $data['projections'] ?? [];
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Reporte Casa Bonita</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; }
                .header { text-align: center; margin-bottom: 20px; }
                .company-name { font-size: 18px; font-weight: bold; color: #2563eb; }
                .report-title { font-size: 14px; margin-top: 5px; }
                .report-date { font-size: 10px; color: #666; margin-top: 5px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .summary { margin-top: 20px; padding: 10px; background-color: #f8f9fa; }
                .summary-item { display: inline-block; margin-right: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">Casa Bonita</div>
                <div class="report-title">Reporte de ' . ucfirst($template['type'] ?? 'Datos') . '</div>
                <div class="report-date">Generado el ' . Carbon::now()->format('d/m/Y H:i') . '</div>
            </div>
            
            <table>
                <thead>
                    <tr>';
        
        foreach ($headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        
        $html .= '
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($dataArray as $item) {
            $html .= '<tr>';
            foreach ($headers as $key => $header) {
                $value = $this->getValueFromItem($item, $key);
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        
        $html .= '
                </tbody>
            </table>';
        
        // Add summary if available
        if (isset($data['summary'])) {
            $html .= '<div class="summary">';
            $html .= '<strong>Resumen:</strong><br>';
            foreach ($data['summary'] as $key => $value) {
                $html .= '<div class="summary-item"><strong>' . ucfirst(str_replace('_', ' ', $key)) . ':</strong> ' . $value . '</div>';
            }
            $html .= '</div>';
        }
        
        $html .= '
        </body>
        </html>';
        
        return $html;
    }

    /**
     * Get headers for export
     */
    protected function getHeaders(array $data, ?array $template = null): array
    {
        if ($template && isset($template['configuration']['columns'])) {
            return $template['configuration']['columns'];
        }

        // Default headers based on data structure
        $dataArray = $data['data'] ?? $data['schedules'] ?? $data['projections'] ?? [];
        
        if (empty($dataArray)) {
            return [];
        }

        $firstItem = reset($dataArray);
        if (is_array($firstItem)) {
            return array_keys($firstItem);
        }

        if (is_object($firstItem)) {
            return array_keys((array) $firstItem);
        }

        return [];
    }

    /**
     * Get value from item
     */
    protected function getValueFromItem($item, string $key): string
    {
        if (is_array($item)) {
            return $item[$key] ?? '';
        }

        if (is_object($item)) {
            return $item->$key ?? '';
        }

        return '';
    }

    /**
     * Generate filename
     */
    protected function generateFilename(string $reportType, string $format): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $randomString = Str::random(6);
        
        return "reporte_{$reportType}_{$timestamp}_{$randomString}.{$format}";
    }
}