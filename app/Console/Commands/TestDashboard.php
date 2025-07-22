<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\HumanResources\Http\Controllers\EmployeeController;
use Illuminate\Http\Request;

class TestDashboard extends Command
{
    protected $signature = 'test:dashboard';
    protected $description = 'Test the admin dashboard endpoint';

    public function handle()
    {
        $this->info('Testing Admin Dashboard Endpoint...');
        
        try {
            // Usar el contenedor de servicios para resolver las dependencias
            $controller = app(EmployeeController::class);
            $request = new Request([
                'month' => date('n'),
                'year' => date('Y')
            ]);
            
            $this->info('Parameters: Month=' . date('n') . ', Year=' . date('Y'));
            
            $response = $controller->adminDashboard($request);
            $content = $response->getContent();
            $data = json_decode($content, true);
            
            $this->info('Response Status: ' . $response->getStatusCode());
            $this->info('Response Data:');
            $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            if (isset($data['data'])) {
                $dashboardData = $data['data'];
                $this->info('=== Data Structure Analysis ===');
                $this->info('Commissions Summary: ' . (isset($dashboardData['commissions_summary']) ? 'Present' : 'Missing'));
                $this->info('Bonuses: ' . (isset($dashboardData['bonuses']) ? 'Present' : 'Missing'));
                $this->info('Top Performers: ' . (isset($dashboardData['top_performers']) ? 'Present' : 'Missing'));
                $this->info('Employees: ' . (isset($dashboardData['employees']) ? 'Present' : 'Missing'));
            }
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}