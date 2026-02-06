<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Security\Models\User;

class CheckAdminPermissions extends Command
{
    protected $signature = 'check:admin-permissions';
    protected $description = 'Check admin user permissions for frontend sidebar';

    public function handle()
    {
        $user = User::where('email', 'admin@casabonita.com')->first();
        
        if (!$user) {
            $this->error('Admin user not found!');
            return;
        }
        
        $this->info("User: {$user->email}");
        $this->info("Role: " . $user->roles->pluck('name')->implode(', '));
        
        $permissions = $user->getAllPermissions()->pluck('name')->toArray();
        $this->info("Total Permissions: " . count($permissions));
        
        // Module .access permissions (required to show modules)
        $accessPerms = [
            'crm.access',
            'security.access',
            'sales.access',
            'inventory.access',
            'finance.access',
            'collections.access',
            'hr.access',
            'accounting.access',
            'reports.access',
            'service-desk.access',
            'audit.access',
        ];
        
        // Child permissions
        $childPerms = [
            'sales.reservations.access',
            'sales.contracts.view',
            'sales.cuts.view',
            'hr.employees.view',
            'hr.teams.view',
            'hr.bonuses.view',
            'hr.commissions.view',
            'hr.payroll.view',
            'collections.view',
            'collections.followups.view',
            'security.users.index',
            'security.roles.view',
            'service-desk.tickets.view',
        ];
        
        $this->info("");
        $this->info("--- MODULE .access PERMISSIONS ---");
        $missingAccess = [];
        foreach ($accessPerms as $perm) {
            if (in_array($perm, $permissions)) {
                $this->info("FOUND: {$perm}");
            } else {
                $missingAccess[] = $perm;
                $this->error("MISSING: {$perm}");
            }
        }
        
        $this->info("");
        $this->info("--- CHILD PERMISSIONS ---");
        $missingChild = [];
        foreach ($childPerms as $perm) {
            if (in_array($perm, $permissions)) {
                $this->info("FOUND: {$perm}");
            } else {
                $missingChild[] = $perm;
                $this->error("MISSING: {$perm}");
            }
        }
        
        $this->info("");
        $this->info("Summary: " . (count($accessPerms) - count($missingAccess)) . "/" . count($accessPerms) . " access perms, " . (count($childPerms) - count($missingChild)) . "/" . count($childPerms) . " child perms");
        
        if (count($missingAccess) > 0 || count($missingChild) > 0) {
            $this->error("Total missing: " . (count($missingAccess) + count($missingChild)));
        } else {
            $this->info("All sidebar permissions are present!");
        }
    }
}
