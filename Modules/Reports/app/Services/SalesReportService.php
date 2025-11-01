<?php

namespace Modules\Reports\app\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesReportService
{
    /**
     * Get complete sales report data for export
     */
    public function getSalesReportData(array $filters = []): array
    {
        // Build query with all necessary joins
        $query = DB::table('contracts as c')
            ->join('clients as cl', 'c.client_id', '=', 'cl.client_id')
            ->join('lots as l', 'c.lot_id', '=', 'l.lot_id')
            ->join('manzanas as m', 'l.manzana_id', '=', 'm.manzana_id')
            ->leftJoin('employees as e', 'c.advisor_id', '=', 'e.employee_id')
            ->leftJoin('users as adv', 'e.user_id', '=', 'adv.user_id')
            ->leftJoin('reservations as r', 'c.reservation_id', '=', 'r.reservation_id')
            ->select(
                // Mes y Oficina
                DB::raw('DATE_FORMAT(c.sign_date, "%Y-%m") as mes'),
                DB::raw('COALESCE(adv.department, "-") as oficina'),
                
                // Asesor y N° Venta
                DB::raw('CONCAT(COALESCE(adv.first_name, ""), " ", COALESCE(adv.last_name, "-")) as asesor'),
                'c.contract_number as num_venta',
                
                // Fecha
                DB::raw('DATE_FORMAT(c.sign_date, "%d/%m/%Y") as fecha'),
                
                // Cliente
                'cl.primary_phone as celular1',
                'cl.secondary_phone as celular2',
                DB::raw('CONCAT(cl.first_name, " ", cl.last_name) as nombre_cliente'),
                
                // Lote
                'm.name as manzana',
                'l.num_lot as lote',
                DB::raw('CONCAT(m.name, "-", l.num_lot) as num_lote'),
                
                // Montos
                'c.total_price as precio_total',
                'c.down_payment as cuota_inicial',
                DB::raw('COALESCE(r.deposit_amount, 0) as separacion'),
                
                // Tipo de cuota inicial (calculado)
                DB::raw('ROUND((c.down_payment / c.total_price) * 100, 2) as porcentaje_inicial'),
                
                // Pagos
                'c.monthly_payment as pago_cuota_directa',
                'c.financing_amount as monto_financiado',
                'c.balloon_payment as cuota_balloon',
                'c.term_months as plazo_meses',
                
                // Cliente info
                DB::raw('TIMESTAMPDIFF(YEAR, cl.date, CURDATE()) as edad'),
                'cl.salary as ingresos',
                'cl.occupation as ocupacion',
                DB::raw('COALESCE(cl.observations, "") as residencia'),
                DB::raw('"Referencia directa" as como_llego'),
                
                // Estado y comentarios
                'c.status as estado',
                DB::raw('"" as comentarios')
            )
            ->where('c.status', '!=', 'cancelled');
        
        // Apply filters
        if (!empty($filters['date_from'])) {
            $query->where('c.sign_date', '>=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $query->where('c.sign_date', '<=', $filters['date_to']);
        }
        
        if (!empty($filters['advisor_id'])) {
            $query->where('c.advisor_id', $filters['advisor_id']);
        }
        
        if (!empty($filters['department'])) {
            $query->where('adv.department', $filters['department']);
        }
        
        $sales = $query->orderBy('c.sign_date', 'desc')->get();
        
        if ($sales->isEmpty()) {
            return $this->getEmptyStructure();
        }
        
        // Build Excel data with proper structure
        $exportData = [];
        
        // Header row with all columns
        $headers = [
            'MES',
            'OFICINA',
            'ASESOR(A)',
            'N° VENTA',
            'FECHA',
            'CELULAR1',
            'CELULAR2',
            'NOMBRE DE CLIENTE',
            'MZ',
            'N° DE LOTE',
            'S/.',
            'CUOTA INICIAL',
            'SEPARACIÓN',
            'T. DE INICIAL (%)',
            'PAGO INICIAL',
            'PAGO DE CUOTA DIRECT',
            'S/ CUOTAS',
            'C. BALLOON',
            'PLAZO (MESES)',
            'COMENTARIOS',
            'EDAD',
            'INGRESOS',
            'OCUPACIÓN',
            'RESIDENCIA',
            'COMO LLEGÓ A NOSOTROS'
        ];
        
        $exportData[] = $headers;
        
        // Data rows
        foreach ($sales as $sale) {
            $exportData[] = [
                $sale->mes,
                $sale->oficina,
                $sale->asesor,
                $sale->num_venta ?? 'N/A',
                $sale->fecha,
                $sale->celular1 ?? '',
                $sale->celular2 ?? '',
                $sale->nombre_cliente,
                $sale->manzana,
                $sale->num_lote,
                'S/ ' . number_format($sale->precio_total, 2),
                'S/ ' . number_format($sale->cuota_inicial, 2),
                'S/ ' . number_format($sale->separacion, 2),
                $sale->porcentaje_inicial . '%',
                'S/ ' . number_format($sale->cuota_inicial, 2),
                'S/ ' . number_format($sale->pago_cuota_directa, 2),
                'S/ ' . number_format($sale->monto_financiado, 2),
                'S/ ' . number_format($sale->cuota_balloon, 2),
                $sale->plazo_meses ?? 'N/A',
                $sale->comentarios,
                $sale->edad ?? 'N/A',
                'S/ ' . number_format($sale->ingresos ?? 0, 2),
                $sale->ocupacion ?? 'N/A',
                $sale->residencia ?? 'N/A',
                $sale->como_llego
            ];
        }
        
        // Summary row
        $totalVentas = $sales->count();
        $totalMonto = $sales->sum('precio_total');
        $totalInicial = $sales->sum('cuota_inicial');
        $totalFinanciado = $sales->sum('monto_financiado');
        
        $exportData[] = []; // Empty row
        $exportData[] = [
            'TOTALES',
            '',
            '',
            $totalVentas . ' ventas',
            '',
            '',
            '',
            '',
            '',
            '',
            'S/ ' . number_format($totalMonto, 2),
            'S/ ' . number_format($totalInicial, 2),
            '',
            '',
            '',
            '',
            'S/ ' . number_format($totalFinanciado, 2),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            ''
        ];
        
        return [
            'Reporte de Ventas' => $exportData
        ];
    }
    
    /**
     * Get empty structure when no data
     */
    private function getEmptyStructure(): array
    {
        return [
            'Reporte de Ventas' => [
                ['MES', 'OFICINA', 'ASESOR(A)', 'N° VENTA', 'FECHA', 'MENSAJE'],
                ['', '', '', '', '', 'No hay ventas en el período seleccionado']
            ]
        ];
    }
}
