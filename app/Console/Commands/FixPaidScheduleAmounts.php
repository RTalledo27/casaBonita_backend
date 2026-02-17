<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixPaidScheduleAmounts extends Command
{
    protected $signature = 'fix:paid-schedule-amounts {--dry-run : Solo mostrar lo que se haría sin ejecutar}';
    protected $description = 'Corregir cuotas con status=pagado que no tienen amount_paid ni logicware_paid_amount';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('Buscando cuotas pagadas sin amount_paid...');

        $query = DB::table('payment_schedules')
            ->where('status', 'pagado')
            ->where(function ($q) {
                $q->whereNull('amount_paid')
                  ->orWhere('amount_paid', 0);
            });

        $total = $query->count();
        $this->info("Encontradas: {$total} cuotas pagadas sin amount_paid");

        if ($total === 0) {
            $this->info('No hay cuotas que corregir.');
            return 0;
        }

        if ($dryRun) {
            $this->warn('Modo dry-run: no se realizaran cambios.');
            $sample = DB::table('payment_schedules')
                ->where('status', 'pagado')
                ->where(function ($q) {
                    $q->whereNull('amount_paid')
                      ->orWhere('amount_paid', 0);
                })
                ->select('schedule_id', 'contract_id', 'installment_number', 'amount', 'amount_paid', 'logicware_paid_amount')
                ->limit(10)
                ->get();

            $this->table(
                ['Schedule ID', 'Contract ID', 'Cuota #', 'Monto', 'Amount Paid', 'LGW Paid'],
                $sample->map(fn ($s) => [
                    $s->schedule_id,
                    $s->contract_id,
                    $s->installment_number,
                    $s->amount,
                    $s->amount_paid ?? 'NULL',
                    $s->logicware_paid_amount ?? 'NULL'
                ])
            );
            return 0;
        }

        if (!$this->confirm("Actualizar {$total} cuotas seteando amount_paid = COALESCE(logicware_paid_amount, amount)?")) {
            $this->info('Cancelado.');
            return 0;
        }

        // amount_paid = logicware_paid_amount si existe, sino = amount
        $updated = DB::table('payment_schedules')
            ->where('status', 'pagado')
            ->where(function ($q) {
                $q->whereNull('amount_paid')
                  ->orWhere('amount_paid', 0);
            })
            ->update([
                'amount_paid' => DB::raw('COALESCE(logicware_paid_amount, amount)')
            ]);

        $this->info("Actualizadas {$updated} cuotas con amount_paid.");

        // Llenar logicware_paid_amount donde este vacio
        $updatedLgw = DB::table('payment_schedules')
            ->where('status', 'pagado')
            ->whereNull('logicware_paid_amount')
            ->whereNotNull('amount_paid')
            ->where('amount_paid', '>', 0)
            ->update([
                'logicware_paid_amount' => DB::raw('amount_paid')
            ]);

        $this->info("Actualizadas {$updatedLgw} cuotas con logicware_paid_amount.");

        $stillMissing = DB::table('payment_schedules')
            ->where('status', 'pagado')
            ->where(function ($q) {
                $q->whereNull('amount_paid')
                  ->orWhere('amount_paid', 0);
            })
            ->count();

        $this->info("Cuotas pagadas aun sin amount_paid: {$stillMissing}");

        return 0;
    }
}
