<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearDatabase extends Command
{
    protected $signature = 'db:clear';
    protected $description = 'Clear all data from database tables except migrations';

    public function handle()
    {
        try {
            $this->info('Clearing database...');
            
            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            
            // Get all tables
            $tables = DB::select('SHOW TABLES');
            $databaseName = DB::getDatabaseName();
            
            foreach ($tables as $table) {
                $tableName = $table->{'Tables_in_' . $databaseName};
                
                // Skip migrations table
                if ($tableName === 'migrations') {
                    continue;
                }
                
                $this->info("Truncating table: {$tableName}");
                DB::table($tableName)->truncate();
            }
            
            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            
            $this->info('Database cleared successfully!');
            
        } catch (\Exception $e) {
            $this->error('Error clearing database: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}