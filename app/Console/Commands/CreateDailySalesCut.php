<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SalesCutService;
use Illuminate\Support\Facades\Log;

class CreateDailySalesCut extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:create-daily-cut {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear corte diario de ventas automáticamente';

    protected SalesCutService $salesCutService;

    /**
     * Create a new command instance.
     */
    public function __construct(SalesCutService $salesCutService)
    {
        parent::__construct();
        $this->salesCutService = $salesCutService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $date = $this->argument('date');

        $this->info('🔄 Creando corte diario de ventas...');

        try {
            $cut = $this->salesCutService->createDailyCut($date);

            $this->line('');
            $this->info('✅ Corte diario creado exitosamente');
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            $this->table(
                ['Métrica', 'Valor'],
                [
                    ['ID del Corte', $cut->cut_id],
                    ['Fecha', $cut->cut_date->format('d/m/Y')],
                    ['Estado', $cut->status],
                    ['Total Ventas', $cut->total_sales_count],
                    ['Ingresos por Ventas', 'S/ ' . number_format($cut->total_revenue, 2)],
                    ['Pagos Recibidos', $cut->total_payments_count],
                    ['Total Cobrado', 'S/ ' . number_format($cut->total_payments_received, 2)],
                    ['Comisiones Generadas', 'S/ ' . number_format($cut->total_commissions, 2)],
                    ['Balance Efectivo', 'S/ ' . number_format($cut->cash_balance, 2)],
                    ['Balance Banco', 'S/ ' . number_format($cut->bank_balance, 2)],
                ]
            );
            $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

            if ($cut->summary_data && isset($cut->summary_data['sales_by_advisor'])) {
                $this->line('');
                $this->info('📊 Ventas por Asesor:');
                $this->table(
                    ['Asesor', 'Ventas', 'Monto Total', 'Comisiones'],
                    array_map(function ($advisor) {
                        return [
                            $advisor['advisor_name'],
                            $advisor['sales_count'],
                            'S/ ' . number_format($advisor['total_amount'], 2),
                            'S/ ' . number_format($advisor['total_commission'], 2),
                        ];
                    }, $cut->summary_data['sales_by_advisor'])
                );
            }

            Log::info('[Command] Corte diario creado exitosamente', [
                'cut_id' => $cut->cut_id,
                'date' => $cut->cut_date->toDateString(),
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error al crear corte diario: ' . $e->getMessage());
            Log::error('[Command] Error al crear corte diario', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
