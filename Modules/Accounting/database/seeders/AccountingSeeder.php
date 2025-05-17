<?php

namespace Modules\Accounting\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class AccountingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'accounting.access',

            'accounting.bank_accounts.view',
            'accounting.bank_accounts.create',
            'accounting.bank_accounts.update',
            'accounting.bank_accounts.delete',

            'accounting.transactions.view',
            'accounting.transactions.create',
            'accounting.transactions.update',
            'accounting.transactions.delete',

            'accounting.invoices.view',
            'accounting.invoices.create',
            'accounting.invoices.update',
            'accounting.invoices.delete',

            'accounting.journal_entries.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
            
    }
}
