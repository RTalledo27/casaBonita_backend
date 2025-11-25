<?php

namespace App\Services\Reports;

use App\Services\ProjectionsService;
use App\Services\SalesReportsService;
use App\Repositories\SalesRepository;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Carbon\Carbon;

class ExcelReportService
{
    protected $projectionsService;
    protected $salesReportsService;
    protected $salesRepository;

    public function __construct(
        ProjectionsService $projectionsService,
        SalesReportsService $salesReportsService,
        SalesRepository $salesRepository
    ) {
        $this->projectionsService = $projectionsService;
        $this->salesReportsService = $salesReportsService;
        $this->salesRepository = $salesRepository;
    }

    /**
     * Generate Monthly Income Report by Advisor
     * Based on Image 1: Columns for each month, grouped by advisor
     */
    public function generateMonthlyIncomeReport(int $year, array $filters = [])
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Ingresos {$year}");

        // Set header
        $sheet->setCellValue('A1', 'INGRESOS CONTRACTUALES CASA BONITA GPAU - ' . $year);
        $sheet->mergeCells('A1:P1');
        $this->styleHeaderCell($sheet, 'A1', '18', true);

        // Column headers
        $columns = ['N° VUELO', 'MES', 'ASESOR(A)', 'NUMERO DE CLIENTE', 'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 
                    'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE', 'TOTALES'];
        
        $col = 'A';
        foreach ($columns as $header) {
            $sheet->setCellValue($col . '3', $header);
            $this->styleHeaderCell($sheet, $col . '3');
            $col++;
        }

        // Get data grouped by advisor
        $data = $this->getMonthlyIncomeByAdvisor($year, $filters);
        
        $row = 4;
        $currentAdvisor = null;
        
        foreach ($data as $item) {
            // Group separator for advisor
            if ($currentAdvisor !== $item['advisor_name']) {
                if ($currentAdvisor !== null) {
                    // Add subtotal row
                    $this->addSubtotalRow($sheet, $row, $item['advisor_name']);
                    $row++;
                }
                $currentAdvisor = $item['advisor_name'];
            }

            // Data row
            $sheet->setCellValue('A' . $row, $item['flight_number']);
            $sheet->setCellValue('B' . $row, $item['month_name']);
            $sheet->setCellValue('C' . $row, $item['advisor_name']);
            $sheet->setCellValue('D' . $row, $item['client_number']);

            // Monthly values (E through P)
            $col = 'E';
            foreach ($item['monthly_values'] as $value) {
                $sheet->setCellValue($col . $row, $value);
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('S/ #,##0.00');
                $col++;
            }

            // Total column
            $sheet->setCellValue('Q' . $row, $item['total']);
            $sheet->getStyle('Q' . $row)->getNumberFormat()->setFormatCode('S/ #,##0.00');

            // Apply red background for separator rows (based on image pattern)
            if ($item['is_separator']) {
                $this->applySeparatorStyle($sheet, $row);
            }

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'Q') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->saveSpreadsheet($spreadsheet, "Ingresos_Mensuales_{$year}");
    }

    /**
     * Generate Detailed Sales Report
     * Based on Image 2: Detailed sales with payment schedules
     */
    public function generateDetailedSalesReport(array $filters = [])
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Ventas Detalladas');

        // Headers
        $headers = [
            'MES', 'DEDICA', 'ASESOR(A)', '% VENTA', 'CREADO', 'CREDARE', 'NOMBRE DE CLIENTE',
            'RANGOS DE CLIENTE B', 'V/', 'CUOTAS', 'PAGO DE CUOTA', 'S/ CUOTAS', 
            '01-ENE-25', '01-FEB-25', '01-MAR-25', '01-ABR-25', '01-MAY-25', 'SALDO POR COBRAR',
            '01-JUN-25', '01-JUL-25', '01-AGO-25', '01-SEP-25', '01-OCT-25', '01-NOV-25', '01-DIC-25', 'SALDO'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $this->styleHeaderCell($sheet, $col . '1');
            $col++;
        }

        // Get sales data
        $salesData = $this->getDetailedSalesData($filters);
        
        $row = 2;
        $currentMonth = null;

        foreach ($salesData as $sale) {
            // Add month separator
            if ($currentMonth !== $sale['month']) {
                if ($currentMonth !== null) {
                    $this->applySeparatorStyle($sheet, $row);
                    $row++;
                }
                $sheet->setCellValue('A' . $row, strtoupper($sale['month']));
                $sheet->mergeCells('A' . $row . ':Z' . $row);
                $this->styleSectionHeader($sheet, 'A' . $row);
                $row++;
                $currentMonth = $sale['month'];
            }

            // Sale data
            $col = 'A';
            foreach ($sale as $key => $value) {
                if ($key !== 'is_separator' && $key !== 'month') {
                    $sheet->setCellValue($col . $row, $value);
                    
                    // Format currency columns
                    if (in_array($key, ['sale_value', 'quota_value', 'balance'])) {
                        $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('S/ #,##0.00');
                    }
                    
                    // Format date columns
                    if (strpos($key, '_date') !== false) {
                        $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('DD/MM/YYYY');
                    }
                    
                    $col++;
                }
            }

            // Apply separator styling if needed
            if ($sale['is_separator']) {
                $this->applySeparatorStyle($sheet, $row);
            }

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'Z') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->saveSpreadsheet($spreadsheet, 'Ventas_Detalladas_' . date('Y-m-d'));
    }

    /**
     * Generate Client Details Report
     * Based on Image 3: Comprehensive client information
     */
    public function generateClientDetailsReport(array $filters = [])
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Detalles Clientes');

        // Headers
        $headers = [
            'C. BALLOON', 'PAGO DE COTA DIRET', 'S/ CUOTAS', 'CONTRATO FIRMADO', 
            'COMENTARIOS', 'EDAD', 'INGRESOS', 'OCUPACIÓN', 'RESIDENCIA', 'COMO LLEGO A NOSOTROS'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $this->styleHeaderCell($sheet, $col . '1');
            $col++;
        }

        // Get client data
        $clientsData = $this->getClientDetailsData($filters);
        
        $row = 2;
        foreach ($clientsData as $client) {
            $sheet->setCellValue('A' . $row, $client['balloon']);
            $sheet->setCellValue('B' . $row, $client['direct_payment']);
            $sheet->setCellValue('C' . $row, $client['quota_amount']);
            $sheet->setCellValue('D' . $row, $client['signed_contract']);
            $sheet->setCellValue('E' . $row, $client['comments']);
            $sheet->setCellValue('F' . $row, $client['age']);
            $sheet->setCellValue('G' . $row, $client['income']);
            $sheet->setCellValue('H' . $row, $client['occupation']);
            $sheet->setCellValue('I' . $row, $client['residence']);
            $sheet->setCellValue('J' . $row, $client['source']);

            // Format currency
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('S/ #,##0.00');
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('S/ #,##0.00');
            $sheet->getStyle('G' . $row)->getNumberFormat()->setFormatCode('S/ #,##0.00');

            // Apply red background for specific cases (e.g., missing data)
            if (empty($client['signed_contract']) || $client['is_separator']) {
                $this->applySeparatorStyle($sheet, $row);
            }

            $row++;
        }

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->saveSpreadsheet($spreadsheet, 'Detalles_Clientes_' . date('Y-m-d'));
    }

    /**
     * Get monthly income data grouped by advisor
     */
    private function getMonthlyIncomeByAdvisor(int $year, array $filters = [])
    {
        // Query to get contracts grouped by advisor and month
        $query = DB::table('sales as s')
            ->join('contracts as c', 's.contract_id', '=', 'c.contract_id')
            ->join('users as u', 's.advisor_id', '=', 'u.id')
            ->whereYear('s.sale_date', $year)
            ->select(
                's.sale_id',
                'u.id as advisor_id',
                'u.first_name',
                'u.last_name',
                's.sale_date',
                'c.contract_number',
                'c.client_name',
                's.total_amount',
                DB::raw('MONTH(s.sale_date) as sale_month')
            );

        // Apply filters
        if (!empty($filters['advisor_id'])) {
            $query->where('s.advisor_id', $filters['advisor_id']);
        }

        if (!empty($filters['office_id'])) {
            $query->where('s.office_id', $filters['office_id']);
        }

        if (!empty($filters['team_id'])) {
            $query->where('s.team_id', $filters['team_id']);
        }

        $results = $query->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->orderBy('s.sale_date')
            ->get();

        // Group by advisor and aggregate monthly data
        $advisorGroups = [];
        foreach ($results as $item) {
            $advisorKey = $item->advisor_id;
            $advisorName = trim($item->first_name . ' ' . $item->last_name);
            
            if (!isset($advisorGroups[$advisorKey])) {
                $advisorGroups[$advisorKey] = [
                    'advisor_name' => $advisorName,
                    'sales' => []
                ];
            }
            
            $advisorGroups[$advisorKey]['sales'][] = $item;
        }

        // Transform data for Excel with proper monthly aggregation
        $data = [];
        $advisorIndex = 1;
        
        foreach ($advisorGroups as $advisorId => $advisorData) {
            // Calculate monthly totals for this advisor
            $monthlyTotals = array_fill(0, 12, 0);
            $salesByMonth = [];
            
            foreach ($advisorData['sales'] as $sale) {
                $month = (int)$sale->sale_month - 1; // 0-indexed
                $monthlyTotals[$month] += $sale->total_amount ?? 0;
                
                if (!isset($salesByMonth[$month])) {
                    $salesByMonth[$month] = [];
                }
                $salesByMonth[$month][] = $sale;
            }
            
            // Add a summary row for this advisor showing all months
            $yearTotal = array_sum($monthlyTotals);
            
            $data[] = [
                'flight_number' => $advisorIndex,
                'month_name' => 'TOTAL',
                'advisor_name' => $advisorData['advisor_name'],
                'client_number' => count($advisorData['sales']) . ' clientes',
                'monthly_values' => $monthlyTotals,
                'total' => $yearTotal,
                'is_separator' => false
            ];
            
            // Add detail rows for each sale
            foreach ($advisorData['sales'] as $sale) {
                $monthlyValues = array_fill(0, 12, 0);
                $month = (int)$sale->sale_month - 1;
                $monthlyValues[$month] = $sale->total_amount ?? 0;
                
                $data[] = [
                    'flight_number' => $sale->contract_number,
                    'month_name' => Carbon::parse($sale->sale_date)->format('F'),
                    'advisor_name' => $advisorData['advisor_name'],
                    'client_number' => $sale->client_name ?? $sale->contract_number,
                    'monthly_values' => $monthlyValues,
                    'total' => $sale->total_amount ?? 0,
                    'is_separator' => false
                ];
            }
            
            $advisorIndex++;
        }

        return $data;
    }

    /**
     * Get detailed sales data with payment schedules
     */
    private function getDetailedSalesData(array $filters = [])
    {
        // Query sales with payment schedules
        $query = DB::table('sales as s')
            ->join('contracts as c', 's.contract_id', '=', 'c.contract_id')
            ->join('users as advisor', 's.advisor_id', '=', 'advisor.id')
            ->leftJoin('teams as t', 's.team_id', '=', 't.team_id')
            ->select(
                's.*',
                'c.contract_number',
                'c.client_name',
                'c.contract_status',
                'advisor.first_name as advisor_first_name',
                'advisor.last_name as advisor_last_name',
                't.team_name',
                DB::raw('MONTH(s.sale_date) as sale_month'),
                DB::raw('MONTHNAME(s.sale_date) as month_name')
            );

        // Apply filters
        if (!empty($filters['year'])) {
            $query->whereYear('s.sale_date', $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->whereMonth('s.sale_date', $filters['month']);
        }
        if (!empty($filters['advisor_id'])) {
            $query->where('s.advisor_id', $filters['advisor_id']);
        }
        if (!empty($filters['team_id'])) {
            $query->where('s.team_id', $filters['team_id']);
        }
        if (!empty($filters['office_id'])) {
            $query->where('s.office_id', $filters['office_id']);
        }

        $sales = $query->orderBy('s.sale_date')->get();

        // Transform data for Excel
        $data = [];
        foreach ($sales as $sale) {
            // Get payment schedule for this sale
            $payments = DB::table('contract_installments')
                ->where('contract_id', $sale->contract_id)
                ->orderBy('installment_number')
                ->get();

            // Calculate monthly payment values (for columns 01-ENE-25, etc.)
            $monthlyPayments = array_fill(0, 12, 0);
            $totalPaid = 0;
            
            foreach ($payments as $payment) {
                if ($payment->due_date) {
                    $month = Carbon::parse($payment->due_date)->month - 1;
                    $monthlyPayments[$month] += $payment->amount ?? 0;
                }
                $totalPaid += $payment->paid_amount ?? 0;
            }

            $balance = ($sale->total_amount ?? 0) - $totalPaid;

            $data[] = [
                'month' => $sale->month_name ?? '',
                'dedica' => '', // Placeholder - add logic if needed
                'advisor' => $sale->advisor_first_name . ' ' . $sale->advisor_last_name,
                'sale_percentage' => '100%', // Adjust based on commission data
                'created_date' => Carbon::parse($sale->created_at)->format('d/m/Y'),
                'credare' => '', // Add logic if available
                'client_name' => $sale->client_name ?? '',
                'client_range' => '', // Add client classification logic
                'sale_value' => $sale->total_amount ?? 0,
                'num_quotas' => count($payments),
                'quota_payment' => $payments[0]->amount ?? 0,
                'quota_total' => $sale->total_amount ?? 0,
                // Monthly payment values
                'jan_payment' => $monthlyPayments[0],
                'feb_payment' => $monthlyPayments[1],
                'mar_payment' => $monthlyPayments[2],
                'apr_payment' => $monthlyPayments[3],
                'may_payment' => $monthlyPayments[4],
                'balance_mid' => $balance / 2, // Mid-year balance
                'jun_payment' => $monthlyPayments[5],
                'jul_payment' => $monthlyPayments[6],
                'aug_payment' => $monthlyPayments[7],
                'sep_payment' => $monthlyPayments[8],
                'oct_payment' => $monthlyPayments[9],
                'nov_payment' => $monthlyPayments[10],
                'dec_payment' => $monthlyPayments[11],
                'balance' => $balance,
                'is_separator' => false
            ];
        }

        return $data;
    }

    /**
     * Get client details data
     */
    private function getClientDetailsData(array $filters = [])
    {
        // Query contracts with client information
        $query = DB::table('contracts as c')
            ->join('sales as s', 'c.contract_id', '=', 's.contract_id')
            ->join('users as advisor', 's.advisor_id', '=', 'advisor.id')
            ->select(
                'c.*',
                's.sale_id',
                's.sale_date',
                's.total_amount',
                's.down_payment',
                'advisor.first_name as advisor_first_name',
                'advisor.last_name as advisor_last_name'
            );

        // Apply filters
        if (!empty($filters['year'])) {
            $query->whereYear('s.sale_date', $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->whereMonth('s.sale_date', $filters['month']);
        }
        if (!empty($filters['status'])) {
            $query->where('c.contract_status', $filters['status']);
        }
        if (!empty($filters['advisor_id'])) {
            $query->where('s.advisor_id', $filters['advisor_id']);
        }

        $contracts = $query->orderBy('s.sale_date', 'desc')->get();

        // Transform data for Excel
        $data = [];
        foreach ($contracts as $contract) {
            // Get payment installments
            $installments = DB::table('contract_installments')
                ->where('contract_id', $contract->contract_id)
                ->get();

            // Calculate quota information
            $numQuotas = count($installments);
            $quotaAmount = $numQuotas > 0 ? ($installments[0]->amount ?? 0) : 0;

            // Determine if contract is signed
            $isSigned = !empty($contract->contract_status) && 
                        $contract->contract_status !== 'pending';

            // Calculate age if birth_date is available
            $age = null;
            if (!empty($contract->client_birth_date)) {
                $age = Carbon::parse($contract->client_birth_date)->age;
            }

            // Parse client additional data if stored as JSON
            $additionalData = [];
            if (!empty($contract->additional_data)) {
                $additionalData = json_decode($contract->additional_data, true) ?? [];
            }

            $data[] = [
                'balloon' => $contract->down_payment ?? 0,
                'direct_payment' => $quotaAmount,
                'quota_amount' => $quotaAmount * $numQuotas,
                'signed_contract' => $isSigned ? 'SI TIENE CONTRATO' : 'NO TIENE CONTRATO',
                'comments' => $contract->notes ?? $additionalData['comments'] ?? '',
                'age' => $age ?? $additionalData['age'] ?? '',
                'income' => $additionalData['monthly_income'] ?? $additionalData['income'] ?? 0,
                'occupation' => $contract->client_occupation ?? $additionalData['occupation'] ?? '',
                'residence' => $contract->client_address ?? $additionalData['city'] ?? '',
                'source' => $additionalData['lead_source'] ?? $additionalData['source'] ?? 'REFERIDO',
                'is_separator' => !$isSigned // Mark unsigned contracts as separators
            ];
        }

        return $data;
    }

    /**
     * Style helper methods
     */
    private function styleHeaderCell($sheet, $cell, $fontSize = '12', $bold = true)
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => [
                'bold' => $bold,
                'size' => $fontSize,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'] // Blue header
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ]);
    }

    private function applySeparatorStyle($sheet, $row)
    {
        $sheet->getStyle('A' . $row . ':Z' . $row)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FF0000'] // Red separator
            ],
            'font' => [
                'color' => ['rgb' => 'FFFFFF'],
                'bold' => true
            ]
        ]);
    }

    private function styleSectionHeader($sheet, $cell)
    {
        $sheet->getStyle($cell)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => '000000']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF00'] // Yellow section header
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ]
        ]);
    }

    private function addSubtotalRow($sheet, $row, $advisorName)
    {
        $sheet->setCellValue('A' . $row, 'TOTAL ' . strtoupper($advisorName));
        $sheet->mergeCells('A' . $row . ':D' . $row);
        
        // Add formulas for subtotals
        for ($col = 'E'; $col <= 'Q'; $col++) {
            // This would calculate the sum for each column
            $sheet->setCellValue($col . $row, "=SUBTOTAL(9,{$col}4:{$col}" . ($row - 1) . ")");
        }

        $this->styleSubtotalRow($sheet, $row);
    }

    private function styleSubtotalRow($sheet, $row)
    {
        $sheet->getStyle('A' . $row . ':Q' . $row)->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2'] // Light blue for subtotals
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN
                ]
            ]
        ]);
    }

    /**
     * Save spreadsheet and return file path
     */
    private function saveSpreadsheet(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);
        $filepath = storage_path("app/reports/{$filename}.xlsx");
        
        // Ensure directory exists
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        $writer->save($filepath);

        return [
            'filepath' => $filepath,
            'filename' => "{$filename}.xlsx",
            'url' => url("storage/reports/{$filename}.xlsx")
        ];
    }
}
