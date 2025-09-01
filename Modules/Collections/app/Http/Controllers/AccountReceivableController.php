<?php

namespace Modules\Collections\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Collections\Models\AccountReceivable;
use Exception;

class AccountReceivableController extends Controller
{
    /**
     * Display a listing of accounts receivable.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = AccountReceivable::with(['client', 'contract', 'collector']);
            
            // Filtros
            if ($request->has('client_id')) {
                $query->byClient($request->client_id);
            }
            
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }
            
            if ($request->has('collector_id')) {
                $query->byCollector($request->collector_id);
            }
            
            if ($request->has('due_date_from')) {
                $query->where('due_date', '>=', $request->due_date_from);
            }
            
            if ($request->has('due_date_to')) {
                $query->where('due_date', '<=', $request->due_date_to);
            }
            
            // Paginación
            $perPage = $request->get('per_page', 15);
            $accountsReceivable = $query->orderBy('due_date', 'desc')->paginate($perPage);
            
            return response()->json([
                'success' => true,
                'data' => $accountsReceivable
            ]);
            
        } catch (Exception $e) {
            Log::error('Error al obtener cuentas por cobrar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las cuentas por cobrar'
            ], 500);
        }
    }
    
    /**
     * Display overdue accounts receivable.
     */
    public function overdue(Request $request): JsonResponse
    {
        try {
            $query = AccountReceivable::with(['client', 'contract', 'collector'])
                ->overdue();
            
            // Filtros adicionales
            if ($request->has('client_id')) {
                $query->byClient($request->client_id);
            }
            
            if ($request->has('collector_id')) {
                $query->byCollector($request->collector_id);
            }
            
            if ($request->has('aging_range')) {
                $agingRange = $request->aging_range;
                switch ($agingRange) {
                    case '1-30':
                        $query->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 1 AND 30');
                        break;
                    case '31-60':
                        $query->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 31 AND 60');
                        break;
                    case '61-90':
                        $query->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 61 AND 90');
                        break;
                    case 'over-90':
                        $query->whereRaw('DATEDIFF(NOW(), due_date) > 90');
                        break;
                }
            }
            
            if ($request->has('min_amount')) {
                $query->where('outstanding_amount', '>=', $request->min_amount);
            }
            
            // Ordenamiento
            $sortBy = $request->get('sort_by', 'due_date');
            $sortOrder = $request->get('sort_order', 'asc');
            $query->orderBy($sortBy, $sortOrder);
            
            // Paginación
            $perPage = $request->get('per_page', 15);
            $overdueAccounts = $query->paginate($perPage);
            
            // Estadísticas adicionales
            $stats = [
                'total_overdue_count' => AccountReceivable::overdue()->count(),
                'total_overdue_amount' => AccountReceivable::overdue()->sum('outstanding_amount'),
                'aging_breakdown' => [
                    '1-30' => AccountReceivable::overdue()->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 1 AND 30')->count(),
                    '31-60' => AccountReceivable::overdue()->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 31 AND 60')->count(),
                    '61-90' => AccountReceivable::overdue()->whereRaw('DATEDIFF(NOW(), due_date) BETWEEN 61 AND 90')->count(),
                    'over-90' => AccountReceivable::overdue()->whereRaw('DATEDIFF(NOW(), due_date) > 90')->count()
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $overdueAccounts,
                'stats' => $stats
            ]);
            
        } catch (Exception $e) {
            Log::error('Error al obtener cuentas por cobrar vencidas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las cuentas por cobrar vencidas'
            ], 500);
        }
    }

    /**
     * Show the specified account receivable.
     */
    public function show($id): JsonResponse
    {
        try {
            $accountReceivable = AccountReceivable::with(['client', 'contract', 'collector', 'payments'])
                ->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $accountReceivable
            ]);
            
        } catch (Exception $e) {
            Log::error('Error al obtener cuenta por cobrar', [
                'ar_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Cuenta por cobrar no encontrada'
            ], 404);
        }
    }
}
