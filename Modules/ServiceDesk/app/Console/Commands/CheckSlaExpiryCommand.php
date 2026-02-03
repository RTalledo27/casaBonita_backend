<?php

namespace Modules\ServiceDesk\Console\Commands;

use Illuminate\Console\Command;
use Modules\ServiceDesk\Repositories\ServiceRequestRepository;
use Modules\ServiceDesk\Services\ServiceDeskNotificationService;

class CheckSlaExpiryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'servicedesk:check-sla 
                            {--hours=4 : Hours threshold for near expiry warning}
                            {--auto-escalate : Automatically escalate expired SLA tickets}';

    /**
     * The console command description.
     */
    protected $description = 'Check for tickets with SLA near expiry or expired, and send notifications';

    protected ServiceRequestRepository $repository;
    protected ServiceDeskNotificationService $notificationService;

    public function __construct(
        ServiceRequestRepository $repository,
        ServiceDeskNotificationService $notificationService
    ) {
        parent::__construct();
        $this->repository = $repository;
        $this->notificationService = $notificationService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hoursThreshold = (int) $this->option('hours');
        $autoEscalate = $this->option('auto-escalate');

        $this->info("Checking for SLA expiry (threshold: {$hoursThreshold} hours)...");

        // 1. Check for tickets nearing SLA expiry
        $nearingExpiry = $this->repository->getTicketsNearingSlaExpiry($hoursThreshold);
        
        if ($nearingExpiry->count() > 0) {
            $this->warn("Found {$nearingExpiry->count()} tickets nearing SLA expiry:");
            
            foreach ($nearingExpiry as $ticket) {
                $hoursRemaining = now()->diffInHours($ticket->sla_due_at, false);
                
                $this->line("  - Ticket #{$ticket->ticket_id}: {$hoursRemaining} hours remaining");
                
                // Send notification
                $this->notificationService->notifySlaNearExpiry($ticket, $hoursRemaining);
            }
        } else {
            $this->info("No tickets nearing SLA expiry.");
        }

        // 2. Check for tickets with expired SLA
        $expired = $this->repository->getTicketsWithExpiredSla();
        
        if ($expired->count() > 0) {
            $this->error("Found {$expired->count()} tickets with EXPIRED SLA:");
            
            foreach ($expired as $ticket) {
                $this->line("  - Ticket #{$ticket->ticket_id}: SLA expired at " . $ticket->sla_due_at->format('Y-m-d H:i'));
                
                // Send expiry notification
                $this->notificationService->notifySlaExpired($ticket);
                
                // Auto-escalate if option is set
                if ($autoEscalate) {
                    $this->repository->escalate($ticket->ticket_id, 'SLA vencido - Escalación automática');
                    $this->warn("    → Ticket #{$ticket->ticket_id} automatically escalated");
                }
            }
        } else {
            $this->info("No tickets with expired SLA.");
        }

        $this->info("SLA check completed.");
        
        return Command::SUCCESS;
    }
}
