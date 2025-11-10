<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\HumanResources\Services\BonusService;
use Carbon\Carbon;

class CalculateMonthlyBonuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bonuses:calculate 
                            {--month= : Mes a procesar (1-12). Por defecto: mes anterior}
                            {--year= : Año a procesar. Por defecto: año actual}
                            {--employee= : ID del empleado específico (opcional)}
                            {--type= : Tipo de bono específico (opcional)}
                            {--dry-run : Simular sin crear bonos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcula bonos automáticos para empleados basados en sus logros y metas';

    protected BonusService $bonusService;

    public function __construct(BonusService $bonusService)
    {
        parent::__construct();
        $this->bonusService = $bonusService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🎁 Iniciando cálculo de bonos automáticos...');
        $this->newLine();

        // Determinar período
        $month = $this->option('month') ?? Carbon::now()->subMonth()->month;
        $year = $this->option('year') ?? Carbon::now()->year;
        $employeeId = $this->option('employee');
        $bonusType = $this->option('type');
        $dryRun = $this->option('dry-run');

        $this->info("📅 Período: {$year}-{$month}");
        if ($employeeId) {
            $this->info("👤 Empleado ID: {$employeeId}");
        }
        if ($bonusType) {
            $this->info("🏷️  Tipo de bono: {$bonusType}");
        }
        if ($dryRun) {
            $this->warn("⚠️  MODO SIMULACIÓN - No se crearán bonos reales");
        }
        $this->newLine();

        try {
            // Procesar bonos automáticos
            $results = $this->bonusService->processAllAutomaticBonuses($month, $year, [
                'employee_id' => $employeeId,
                'bonus_type' => $bonusType,
                'dry_run' => $dryRun
            ]);

            // Mostrar resultados
            $this->displayResults($results);

            $this->newLine();
            $this->info('✅ Cálculo de bonos completado exitosamente');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Error al calcular bonos: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }

    protected function displayResults(array $results): void
    {
        $this->newLine();
        $this->info('📊 RESUMEN DE BONOS CALCULADOS');
        $this->info('─────────────────────────────────────────');

        $totalBonuses = 0;
        $totalAmount = 0;

        foreach ($results as $type => $data) {
            $count = is_array($data) ? count($data) : 0;
            $amount = is_array($data) ? collect($data)->sum('bonus_amount') : 0;

            $totalBonuses += $count;
            $totalAmount += $amount;

            $this->line(sprintf(
                '  %s: %d bonos - S/ %s',
                ucfirst($type),
                $count,
                number_format($amount, 2)
            ));
        }

        $this->newLine();
        $this->info(sprintf('  TOTAL: %d bonos - S/ %s', $totalBonuses, number_format($totalAmount, 2)));
        $this->newLine();
    }
}

