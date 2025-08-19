<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CheckExcelHeaders extends Command
{
    protected $signature = 'check:excel-headers {file?}';
    protected $description = 'Check Excel file headers';

    public function handle()
    {
        $filePath = $this->argument('file') ?? 'storage/app/public/imports/contratos_prueba.xlsx';
        
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get first row (headers)
            $headers = [];
            $highestColumn = $worksheet->getHighestColumn();
            $columnRange = range('A', $highestColumn);
            
            foreach ($columnRange as $col) {
                $cellValue = $worksheet->getCell($col . '1')->getValue();
                $headers[] = $cellValue;
            }
            
            $this->info('Excel Headers Found:');
            foreach ($headers as $index => $header) {
                $this->line("Column {$index}: '{$header}'");
            }
            
            // Also show first data row
            $this->info('\nFirst Data Row:');
            $firstDataRow = [];
            foreach ($columnRange as $col) {
                $cellValue = $worksheet->getCell($col . '2')->getValue();
                $firstDataRow[] = $cellValue;
            }
            
            foreach ($firstDataRow as $index => $value) {
                $this->line("Column {$index}: '{$value}'");
            }
            
        } catch (\Exception $e) {
            $this->error('Error reading Excel file: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}