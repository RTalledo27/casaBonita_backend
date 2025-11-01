<?php

namespace App\Services;

use App\Repositories\SalesRepository;
use App\Repositories\PaymentsRepository;
use App\Repositories\ProjectionsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ReportsService
{
    protected $salesRepository;
    protected $paymentsRepository;
    protected $projectionsRepository;

    public function __construct(
        SalesRepository $salesRepository,
        PaymentsRepository $paymentsRepository,
        ProjectionsRepository $projectionsRepository
    ) {
        $this->salesRepository = $salesRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->projectionsRepository = $projectionsRepository;
    }

    /**
     * Get dashboard metrics and summary data
     */
    public function getDashboardMetrics(array $filters = []): array
    {
        $cacheKey = 'dashboard_metrics_' . md5(serialize($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($filters) {
            $startDate = $filters['start_date'] ?? Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = $filters['end_date'] ?? Carbon::now()->endOfMonth()->format('Y-m-d');
            $officeId = $filters['office_id'] ?? null;

            // Sales metrics
            $salesMetrics = $this->salesRepository->getSalesMetrics($startDate, $endDate, $officeId);
            
            // Payment metrics
            $paymentMetrics = $this->paymentsRepository->getPaymentMetrics($startDate, $endDate, $officeId);
            
            // Projection metrics
            $projectionMetrics = $this->projectionsRepository->getProjectionMetrics($officeId);

            return [
                'sales' => [
                    'total_sales' => $salesMetrics['total_sales'] ?? 0,
                    'total_amount' => $salesMetrics['total_amount'] ?? 0,
                    'average_sale' => $salesMetrics['average_sale'] ?? 0,
                    'growth_rate' => $salesMetrics['growth_rate'] ?? 0,
                ],
                'payments' => [
                    'total_collected' => $paymentMetrics['total_collected'] ?? 0,
                    'pending_amount' => $paymentMetrics['pending_amount'] ?? 0,
                    'overdue_count' => $paymentMetrics['overdue_count'] ?? 0,
                    'collection_rate' => $paymentMetrics['collection_rate'] ?? 0,
                ],
                'projections' => [
                    'next_month_revenue' => $projectionMetrics['next_month_revenue'] ?? 0,
                    'quarterly_projection' => $projectionMetrics['quarterly_projection'] ?? 0,
                    'annual_projection' => $projectionMetrics['annual_projection'] ?? 0,
                ],
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'office_id' => $officeId,
                ]
            ];
        });
    }

    /**
     * Get report templates
     */
    public function getTemplates(?string $type = null): array
    {
        $query = DB::table('report_templates')
            ->where('is_active', true)
            ->orderBy('name');

        if ($type) {
            $query->where('type', $type);
        }

        $templates = $query->get()->toArray();

        return array_map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'type' => $template->type,
                'configuration' => json_decode($template->configuration, true),
                'filters' => json_decode($template->filters, true),
                'format' => $template->format,
                'created_at' => $template->created_at,
            ];
        }, $templates);
    }

    /**
     * Get generated reports history
     */
    public function getReportsHistory(array $filters = []): array
    {
        $query = DB::table('generated_reports as gr')
            ->leftJoin('report_templates as rt', 'gr.template_id', '=', 'rt.id')
            ->leftJoin('users as u', 'gr.user_id', '=', 'u.id')
            ->select([
                'gr.id',
                'gr.file_name',
                'gr.format',
                'gr.status',
                'gr.generated_at',
                'gr.expires_at',
                'rt.name as template_name',
                'rt.type as report_type',
                'u.name as user_name',
                'gr.parameters'
            ])
            ->orderBy('gr.generated_at', 'desc');

        // Apply filters
        if (!empty($filters['user_id'])) {
            $query->where('gr.user_id', $filters['user_id']);
        }

        if (!empty($filters['format'])) {
            $query->where('gr.format', $filters['format']);
        }

        if (!empty($filters['status'])) {
            $query->where('gr.status', $filters['status']);
        }

        if (!empty($filters['start_date'])) {
            $query->where('gr.generated_at', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('gr.generated_at', '<=', $filters['end_date']);
        }

        $reports = $query->paginate(15);

        return [
            'data' => $reports->items(),
            'pagination' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ]
        ];
    }

    /**
     * Create a new report template
     */
    public function createTemplate(array $data): array
    {
        $templateId = DB::table('report_templates')->insertGetId([
            'name' => $data['name'],
            'type' => $data['type'],
            'configuration' => json_encode($data['configuration']),
            'filters' => json_encode($data['filters'] ?? []),
            'format' => $data['format'] ?? 'excel',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->getTemplateById($templateId);
    }

    /**
     * Update an existing report template
     */
    public function updateTemplate(int $id, array $data): array
    {
        DB::table('report_templates')
            ->where('id', $id)
            ->update([
                'name' => $data['name'],
                'configuration' => json_encode($data['configuration']),
                'filters' => json_encode($data['filters'] ?? []),
                'format' => $data['format'] ?? 'excel',
                'updated_at' => now(),
            ]);

        return $this->getTemplateById($id);
    }

    /**
     * Get template by ID
     */
    public function getTemplateById(int $id): array
    {
        $template = DB::table('report_templates')
            ->where('id', $id)
            ->first();

        if (!$template) {
            throw new \Exception('Template not found');
        }

        return [
            'id' => $template->id,
            'name' => $template->name,
            'type' => $template->type,
            'configuration' => json_decode($template->configuration, true),
            'filters' => json_decode($template->filters, true),
            'format' => $template->format,
            'is_active' => $template->is_active,
            'created_at' => $template->created_at,
            'updated_at' => $template->updated_at,
        ];
    }

    /**
     * Delete a report template
     */
    public function deleteTemplate(int $id): bool
    {
        return DB::table('report_templates')
            ->where('id', $id)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Log generated report
     */
    public function logGeneratedReport(array $data): int
    {
        return DB::table('generated_reports')->insertGetId([
            'template_id' => $data['template_id'] ?? null,
            'user_id' => $data['user_id'],
            'file_name' => $data['file_name'],
            'file_path' => $data['file_path'],
            'format' => $data['format'],
            'parameters' => json_encode($data['parameters'] ?? []),
            'status' => $data['status'] ?? 'generating',
            'generated_at' => now(),
            'expires_at' => $data['expires_at'] ?? now()->addDays(7),
        ]);
    }

    /**
     * Update generated report status
     */
    public function updateGeneratedReportStatus(int $id, string $status): bool
    {
        return DB::table('generated_reports')
            ->where('id', $id)
            ->update([
                'status' => $status,
                'updated_at' => now(),
            ]) > 0;
    }
}