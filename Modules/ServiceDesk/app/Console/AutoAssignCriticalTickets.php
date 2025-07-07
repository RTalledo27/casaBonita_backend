<?php

namespace Modules\ServiceDesk\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Security\Models\User;
use Modules\ServiceDesk\Models\ServiceRequest;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class AutoAssignCriticalTickets extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tickets:auto-assign-critical';

    /**
     * The console command description.
     */
    protected $description = 'Asigna automáticamente tickets críticos a agentes sin asignaciones';


    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Buscar tickets críticos sin asignar y abiertos por más de 30min
        $limit = Carbon::now()->subMinutes(30);

        $tickets = ServiceRequest::where('priority', 'critica')
            ->where('status', 'abierto')
            ->whereNull('assigned_to')
            ->where('opened_at', '<=', $limit)
            ->get();

        if ($tickets->isEmpty()) {
            $this->info('No hay tickets críticos sin asignar.');
            return 0;
        }

        // 2. Buscar agentes con menos asignaciones activas (ejemplo: user con rol "soporte" y sin tickets abiertos)
        foreach ($tickets as $ticket) {
            $agent = User::where('status', 'active')
                //->role('soporte') // Si tienes roles/Spatie
                ->whereDoesntHave('assignedTickets', function ($query) {
                    $query->where('status', 'abierto');
                })
                ->first();

            if ($agent) {
                $ticket->assigned_to = $agent->user_id;
                $ticket->save();

                // Opcional: Notifica al agente (mail, evento, etc.)
                $this->info("Ticket #{$ticket->ticket_id} asignado a usuario {$agent->username}");
            } else {
                $this->warn("No hay agentes sin asignaciones para ticket #{$ticket->ticket_id}");
            }
        }

        return 0;
    }
}
