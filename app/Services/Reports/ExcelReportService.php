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
        $this->styleHeaderCell($sheet, 'A1', 18, true);

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
                $sheet->getStyle($col . $row)->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
                $col++;
            }

            // Total column
            $sheet->setCellValue('Q' . $row, $item['total']);
            $sheet->getStyle('Q' . $row)->getNumberFormat()->setFormatCode('"S/ " #,##0.00');

            // Apply red background for separator rows (based on image pattern)
            if ($item['is_separator']) {
                $this->applySeparatorStyle($sheet, $row);
            }

            $row++;
        }

        // Apply borders to the data range (excluding headers)
        $lastRow = $row - 1;
        if ($lastRow >= 4) { // Data starts at row 4
            $sheet->getStyle('A4:Q' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ]);
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
        $period = null;
        if (!empty($filters['year']) && !empty($filters['month'])) {
            $period = sprintf('%04d-%02d', (int)$filters['year'], (int)$filters['month']);
        } elseif (!empty($filters['year'])) {
            $period = (string) $filters['year'];
        }
        $sheet->setTitle('Ventas Detalladas' . ($period ? ' ' . $period : ''));

        // Grouped headers
        $sheet->setCellValue('K1', 'REEMBOLSO');
        $sheet->mergeCells('K1:P1');
        $this->styleSectionHeader($sheet, 'K1');

        $sheet->setCellValue('Q1', 'CUOTA INICIAL');
        $sheet->mergeCells('Q1:V1');
        $this->styleSectionHeader($sheet, 'Q1');

        // Column headers row 2
        $headers = [
            'MES', 'OFICINA', 'ASESOR(A)', 'N° CONTRATO', 'FECHA', 'CELULAR1', 'CELULAR2',
            'NOMBRE DE CLIENTE', 'MZ', 'N° DE LOTE', 'S/.', 'CUOTA INICIAL', 'SEPARACION',
            'CONTADO', 'FINANCIADO', 'N° DE CUOTAS',
            'T. DE INICIAL 1', 'T. DE INICIAL 2', 'T. DE INICIAL 3', 'T. DE INICIAL 4', 'T. DE INICIAL 5',
            'P.INICIAL', 'C. BALLOON', 'PLAZO (MESES)', 'COMENTARIOS', 'EDAD', 'INGRESOS',
            'OCUPACIÓN', 'RESIDENCIA', 'COMO LLEGÓ A NOSOTROS'
        ];

        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '2', $header);
            $this->styleHeaderCell($sheet, $col . '2');
            $col++;
        }

        // Get sales data
        $salesData = $this->getDetailedSalesData($filters);
        
        $row = 3;
        $currentMonth = null;
        $monthStartRow = null;

        foreach ($salesData as $sale) {
            // Month block
            if ($currentMonth !== $sale['month']) {
                if ($currentMonth !== null && $monthStartRow !== null) {
                    $sheet->mergeCells('A' . $monthStartRow . ':A' . ($row - 1));
                    $sheet->setCellValue('A' . $monthStartRow, $sale['month_name_full']);
                    $style = $sheet->getStyle('A' . $monthStartRow . ':A' . ($row - 1));
                    $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
                    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                    $style->getAlignment()->setTextRotation(90);
                }
                $currentMonth = $sale['month'];
                $monthStartRow = $row;
            }

            $sheet->setCellValue('A' . $row, $sale['month_code']);
            $sheet->setCellValue('B' . $row, $sale['office']);
            $sheet->setCellValue('C' . $row, $sale['advisor']);
            $sheet->setCellValue('D' . $row, $sale['sale_number']);
            $sheet->setCellValue('E' . $row, $sale['date']);
            $sheet->setCellValue('F' . $row, $sale['phone1']);
            $sheet->setCellValue('G' . $row, $sale['phone2']);
            $sheet->setCellValue('H' . $row, $sale['client_name']);
            $sheet->setCellValue('I' . $row, $sale['mz']);
            $sheet->setCellValue('J' . $row, $sale['lot']);
            $sheet->setCellValue('K' . $row, $sale['total_amount']);
            $sheet->setCellValue('L' . $row, $sale['down_payment']);
            $sheet->setCellValue('M' . $row, $sale['separation_amount']);
            $sheet->setCellValue('N' . $row, $sale['cash_amount']);
            $sheet->setCellValue('O' . $row, $sale['financed_amount']);
            $sheet->setCellValue('P' . $row, $sale['num_installments']);
            $sheet->setCellValue('Q' . $row, $sale['initials'][0] ?? 0);
            $sheet->setCellValue('R' . $row, $sale['initials'][1] ?? 0);
            $sheet->setCellValue('S' . $row, $sale['initials'][2] ?? 0);
            $sheet->setCellValue('T' . $row, $sale['initials'][3] ?? 0);
            $sheet->setCellValue('U' . $row, $sale['initials'][4] ?? 0);
            $sheet->setCellValue('V' . $row, $sale['initial_sum']);
            $sheet->setCellValue('W' . $row, $sale['balloon']);
            $sheet->setCellValue('X' . $row, $sale['term']);
            $sheet->setCellValue('Y' . $row, $sale['comments']);
            $sheet->setCellValue('Z' . $row, $sale['age']);
            $sheet->setCellValue('AA' . $row, $sale['income']);
            $sheet->setCellValue('AB' . $row, $sale['occupation']);
            $sheet->setCellValue('AC' . $row, $sale['residence']);
            $sheet->setCellValue('AD' . $row, $sale['source']);

            // Format currency columns
            foreach (['K','L','M','N','O','Q','R','S','T','U','V','W','AA'] as $currencyCol) {
                $sheet->getStyle($currencyCol . $row)->getNumberFormat()->setFormatCode('"S/ " #,##0.00');
            }

            // No percentage formatting; reserva es monto en S/

            $row++;
        }

        // Close last month block
        if ($currentMonth !== null && $monthStartRow !== null) {
            $sheet->mergeCells('A' . $monthStartRow . ':A' . ($row - 1));
            $sheet->setCellValue('A' . $monthStartRow, $salesData[count($salesData)-1]['month_name_full']);
            $style = $sheet->getStyle('A' . $monthStartRow . ':A' . ($row - 1));
            $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFFF00');
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $style->getAlignment()->setTextRotation(90);
        }

        // Apply borders to the data range (excluding headers)
        $lastRow = $row - 1;
        if ($lastRow >= 3) {
            $sheet->getStyle('A3:AD' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ]);
        }

        // Auto-size columns
        foreach (range('A', 'AD') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        // Explicit widths for readability
        $sheet->getColumnDimension('A')->setWidth(8);
        $sheet->getColumnDimension('B')->setWidth(18);
        $sheet->getColumnDimension('C')->setWidth(26);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(12);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(34);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(16);
        $sheet->getColumnDimension('L')->setWidth(16);
        $sheet->getColumnDimension('M')->setWidth(16);
        $sheet->getColumnDimension('N')->setWidth(16);
        $sheet->getColumnDimension('O')->setWidth(16);
        $sheet->getColumnDimension('P')->setWidth(12);
        $sheet->getColumnDimension('Q')->setWidth(14);
        $sheet->getColumnDimension('R')->setWidth(14);
        $sheet->getColumnDimension('S')->setWidth(14);
        $sheet->getColumnDimension('T')->setWidth(14);
        $sheet->getColumnDimension('U')->setWidth(14);
        $sheet->getColumnDimension('V')->setWidth(14);
        $sheet->getColumnDimension('W')->setWidth(14);
        $sheet->getColumnDimension('X')->setWidth(14);
        $sheet->getColumnDimension('Y')->setWidth(12);
        $sheet->getColumnDimension('Z')->setWidth(36);
        $sheet->getColumnDimension('AA')->setWidth(10);
        $sheet->getColumnDimension('AB')->setWidth(16);
        $sheet->getColumnDimension('AC')->setWidth(18);
        $sheet->getColumnDimension('AD')->setWidth(24);

        $filename = 'Ventas_Detalladas_' . ($period ?? date('Y-m-d'));
        return $this->saveSpreadsheet($spreadsheet, $filename);
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

        // Apply borders to the data range (excluding headers)
        $lastRow = $row - 1;
        if ($lastRow >= 2) {
            $sheet->getStyle('A2:J' . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000']
                    ]
                ]
            ]);
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
        // Query contracts (no sales table exists)
        $query = DB::table('contracts as c')
            ->leftJoin('users as u', 'c.advisor_id', '=', 'u.user_id')
            ->leftJoin('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->whereYear('c.sign_date', $year)
            ->select(
                'c.contract_id',
                'u.user_id as advisor_id',
                'u.first_name',
                'u.last_name',
                'c.sign_date',
                'c.contract_number',
                'cl.first_name as client_first_name',
                'cl.last_name as client_last_name',
                'c.total_price as total_amount',
                DB::raw('MONTH(c.sign_date) as sale_month')
            );

        // Apply filters
        if (!empty($filters['advisor_id'])) {
            $query->where('c.advisor_id', $filters['advisor_id']);
        }

        // Note: team_id filter removed - contracts table doesn't have team_id
        // Team relationship would need to come through advisor->team if needed

        if (!empty($filters['startDate'])) {
            $query->whereDate('c.sign_date', '>=', $filters['startDate']);
        }

        if (!empty($filters['endDate'])) {
            $query->whereDate('c.sign_date', '<=', $filters['endDate']);
        }

        $results = $query->orderBy('u.last_name')
            ->orderBy('u.first_name')
            ->orderBy('c.sign_date')
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
                'total' => (float) $yearTotal,
                'is_separator' => false
            ];
            
            // Add detail rows for each sale
            foreach ($advisorData['sales'] as $sale) {
                $monthlyValues = array_fill(0, 12, 0);
                $month = (int)$sale->sale_month - 1;
                $monthlyValues[$month] = (float) ($sale->total_amount ?? 0);
                $clientName = trim(($sale->client_first_name ?? '') . ' ' . ($sale->client_last_name ?? ''));
                
                $data[] = [
                    'flight_number' => $sale->contract_number,
                    'month_name' => Carbon::parse($sale->sign_date)->format('F'),
                    'advisor_name' => $advisorData['advisor_name'],
                    'client_number' => $clientName ?: $sale->contract_number,
                    'monthly_values' => $monthlyValues,
                    'total' => (float) ($sale->total_amount ?? 0),
                    'is_separator' => false
                ];
            }
            
            $advisorIndex++;
        }

        return $data;
    }

    /**
     * Get detailed sales data matching the original export format
     */
    private function getDetailedSalesData(array $filters = [])
    {
        // Query contracts with correct joins through employees table
        $query = DB::table('contracts as c')
            ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
            ->leftJoin('users as advisor', 'e.user_id', '=', 'advisor.user_id')
            ->leftJoin('users as ua', 'c.advisor_id', '=', 'ua.user_id')
            ->leftJoin('teams as t', 'e.team_id', '=', 't.team_id')
            ->leftJoin('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->leftJoin('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->leftJoin('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->leftJoin('employees as re', 'r.advisor_id', '=', 're.employee_id')
            ->leftJoin('users as ru', 're.user_id', '=', 'ru.user_id')
            ->leftJoin('teams as rt', 're.team_id', '=', 'rt.team_id')
            ->select(
                'c.contract_id',
                'c.contract_number',
                'c.sign_date',
                'c.total_price',
                'c.down_payment',
                'c.monthly_payment',
                'c.balloon_payment',
                'c.term_months',
                'c.notes',
                'c.source',
                't.team_name as office_name',
                'advisor.first_name as advisor_first_name',
                'advisor.last_name as advisor_last_name',
                'ua.first_name as advisor_user_first_name',
                'ua.last_name as advisor_user_last_name',
                'ru.first_name as reservation_advisor_first_name',
                'ru.last_name as reservation_advisor_last_name',
                'cl.first_name as client_first_name',
                'cl.last_name as client_last_name',
                'cl.primary_phone',
                'cl.secondary_phone',
                'cl.occupation',
                'cl.salary',
                'l.num_lot',
                'l.manzana_id',
                'l.external_code',
                'm.name as manzana_name',
                'r.deposit_amount',
                'rt.team_name as reservation_team_name',
                DB::raw('MONTH(c.sign_date) as sale_month'),
                DB::raw('MONTHNAME(c.sign_date) as month_name')
            );

        // Apply filters
        if (!empty($filters['year'])) {
            $query->whereYear('c.sign_date', $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->whereMonth('c.sign_date', $filters['month']);
        }
        if (!empty($filters['advisor_id'])) {
            $query->where('c.advisor_id', $filters['advisor_id']);
        }
        if (!empty($filters['team_id'])) {
            $query->where('e.team_id', $filters['team_id']);
        }
        if (!empty($filters['office_id'])) {
            $query->where('e.team_id', $filters['office_id']);
        }
        if (!empty($filters['startDate'])) {
            $query->whereDate('c.sign_date', '>=', $filters['startDate']);
        }
        if (!empty($filters['endDate'])) {
            $query->whereDate('c.sign_date', '<=', $filters['endDate']);
        }

        $contracts = $query->orderBy('c.sign_date', 'asc')->get();

        // Transform data for Excel
        $data = [];
        foreach ($contracts as $contract) {
            $clientName = trim(($contract->client_first_name ?? '') . ' ' . ($contract->client_last_name ?? ''));
            $advisorNamePrimary = trim(($contract->advisor_first_name ?? '') . ' ' . ($contract->advisor_last_name ?? ''));
            $advisorNameDirect = trim(($contract->advisor_user_first_name ?? '') . ' ' . ($contract->advisor_user_last_name ?? ''));
            $advisorNameFromReservation = trim(($contract->reservation_advisor_first_name ?? '') . ' ' . ($contract->reservation_advisor_last_name ?? ''));
            $advisorName = $advisorNamePrimary ?: ($advisorNameDirect ?: ($advisorNameFromReservation ?: '-'));

            $officeName = $contract->office_name ?? ($contract->reservation_team_name ?? '-');
            
            // Calculate initial percentage
            $initialPercent = 0;
            if ($contract->total_price > 0) {
                // Fraction for Excel percentage formatting (e.g., 0.0199 => 1.99%)
                $initialPercent = ($contract->down_payment / $contract->total_price);
            }
            
            // Calculate total quota amount (monthly payment * term)
            $quotaAmount = ($contract->monthly_payment ?? 0) * ($contract->term_months ?? 0);
            
            // Age not available in clients table
            $age = null;
            
            // Schedules and template-based calculations
            $schedules = DB::table('payment_schedules')
                ->where('contract_id', $contract->contract_id)
                ->orderBy('due_date', 'asc')
                ->get();

            $initialsArr = [];
            $numInstallments = 0;
            // Reserva desde la tabla reservations (pre-contrato)
            $separationAmount = (float) ($contract->deposit_amount ?? 0.0);
            $balloonAmount = 0.0;
            $preContractInitialsSum = 0.0;
            $signDate = !empty($contract->sign_date) ? Carbon::parse($contract->sign_date) : null;

            foreach ($schedules as $sc) {
                $type = $sc->type ?? '';
                $amount = (float) ($sc->amount ?? 0);
                $dueDate = !empty($sc->due_date) ? Carbon::parse($sc->due_date) : null;

                if ($type === 'inicial') {
                    // separar iniciales pre-contrato para reserva y post-contrato para T. DE INICIAL
                    if ($signDate && $dueDate && $dueDate->lt($signDate)) {
                        $preContractInitialsSum += $amount;
                        if ($separationAmount <= 0 && stripos($sc->notes ?? '', 'separ') !== false) {
                            $separationAmount = $amount;
                        }
                    } else {
                        if (count($initialsArr) < 5) {
                            $initialsArr[] = $amount;
                        }
                        // Si alguna cuota marcada como separación ocurre post-firma, aún debe reflejarse
                        if ($separationAmount <= 0 && stripos($sc->notes ?? '', 'separ') !== false) {
                            $separationAmount = $amount;
                        }
                    }
                } elseif ($type === 'financiamiento') {
                    $numInstallments++;
                } elseif ($type === 'balon') {
                    $balloonAmount = $amount;
                }
            }

            if ($separationAmount <= 0 && $preContractInitialsSum > 0) {
                $separationAmount = $preContractInitialsSum;
            }

            $initialSum = array_sum($initialsArr);
            $financedAmount = max(0, (float) ($contract->total_price ?? 0) - (float) ($contract->down_payment ?? 0));

            $monthCode = strtoupper(substr(Carbon::parse($contract->sign_date)->translatedFormat('M'), 0, 3));
            $monthFull = strtoupper(Carbon::parse($contract->sign_date)->translatedFormat('F'));

            // Extract MZ and LOTE from external_code when available (e.g., "F2-03" → MZ=F2, LOTE=3)
            $ext = $contract->external_code ?? null;
            if ($ext && strpos($ext, '-') !== false) {
                [$extMz, $extLot] = explode('-', $ext, 2);
                $extMz = trim($extMz);
                $extLot = ltrim(trim($extLot), '0');
                $mappedMz = $extMz;
                $mappedLot = $extLot !== '' ? $extLot : ($contract->num_lot ?? '-');
            } else {
                $mappedMz = $contract->manzana_name ?? ($contract->manzana_id ?? '-');
                $mappedLot = $contract->num_lot ?? '-';
            }

            $data[] = [
                'month' => $contract->sign_date ? Carbon::parse($contract->sign_date)->format('Y-m') : '-',
                'month_code' => $monthCode,
                'month_name_full' => $monthFull,
                'office' => $officeName,
                'advisor' => $advisorName ?: '-',
                'sale_number' => $contract->contract_number ?? '',
                'date' => $contract->sign_date ? Carbon::parse($contract->sign_date)->format('d/m/Y') : '',
                'phone1' => $contract->primary_phone ?? '',
                'phone2' => $contract->secondary_phone ?? '',
                'client_name' => $clientName,
                'mz' => $mappedMz,
                'lot' => $mappedLot,
                'total_amount' => (float) ($contract->total_price ?? 0),
                'down_payment' => (float) ($contract->down_payment ?? 0),
                'separation' => (float) $separationAmount,
                'initial_percent' => (float) $initialPercent,
                'initial_payment' => (float) ($contract->down_payment ?? 0),
                'direct_quota' => (float) ($contract->monthly_payment ?? 0),
                'quota_amount' => (float) $quotaAmount,
                'cash_amount' => 0.0,
                'financed_amount' => (float) $financedAmount,
                'num_installments' => (int) $numInstallments,
                'separation_amount' => (float) $separationAmount,
                'initials' => $initialsArr,
                'initial_sum' => (float) $initialSum,
                'balloon' => (float) ($balloonAmount ?: ($contract->balloon_payment ?? 0)),
                'term' => (int) ($contract->term_months ?? 0),
                'comments' => $contract->notes ?? '',
                'age' => $age ?? '',
                'income' => (float) ($contract->salary ?? 0),
                'occupation' => $contract->occupation ?? '',
                'residence' => '',
                'source' => $contract->source ?? '',
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
            ->leftJoin('users as advisor', 'c.advisor_id', '=', 'advisor.user_id')
            ->leftJoin('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->select(
                'c.contract_id',
                'c.sign_date',
                'c.total_price',
                'c.down_payment',
                'c.balloon_payment',
                'c.monthly_payment',
                'c.term_months',
                'c.notes',
                'c.status',
                'c.source',
                'advisor.first_name as advisor_first_name',
                'advisor.last_name as advisor_last_name',
                'cl.occupation',
                'cl.salary'
            );

        // Apply filters
        if (!empty($filters['year'])) {
            $query->whereYear('c.sign_date', $filters['year']);
        }
        if (!empty($filters['month'])) {
            $query->whereMonth('c.sign_date', $filters['month']);
        }
        if (!empty($filters['status'])) {
            $query->where('c.status', $filters['status']);
        }
        if (!empty($filters['advisor_id'])) {
            $query->where('c.advisor_id', $filters['advisor_id']);
        }
        if (!empty($filters['startDate'])) {
            $query->whereDate('c.sign_date', '>=', $filters['startDate']);
        }
        if (!empty($filters['endDate'])) {
            $query->whereDate('c.sign_date', '<=', $filters['endDate']);
        }

        $contracts = $query->orderBy('c.sign_date', 'desc')->get();

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
            $isSigned = !empty($contract->status) && 
                        $contract->status !== 'pending';

            // Age not available in clients table
            $age = null;

            $data[] = [
                'balloon' => (float) ($contract->balloon_payment ?? 0),
                'direct_payment' => (float) ($contract->monthly_payment ?? 0),
                'quota_amount' => (float) ($quotaAmount * $numQuotas),
                'signed_contract' => $isSigned ? 'SI TIENE CONTRATO' : 'NO TIENE CONTRATO',
                'comments' => $contract->notes ?? '',
                'age' => $age ?? '',
                'income' => (float) ($contract->salary ?? 0),
                'occupation' => $contract->occupation ?? '',
                'residence' => '',
                'source' => $contract->source ?? 'REFERIDO',
                'is_separator' => !$isSigned
            ];
        }

        return $data;
    }

    /**
     * Style helper methods
     */
    private function styleHeaderCell($sheet, $cell, $fontSize = 12, $bold = true)
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
