<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\UserActivityLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Http\Requests\StoreLotRequest;
use Modules\Inventory\Http\Requests\UpdateLotRequest;
use Modules\Inventory\Models\Lot;
use Modules\Inventory\Models\LotFinancialTemplate;
use Modules\Inventory\Models\ManzanaFinancingRule;
use Modules\Inventory\Repositories\LotRepository;
use Modules\Inventory\Transformers\LotResource;
use Modules\Services\PusherNotifier;
use Pusher\Pusher;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Modules\Inventory\Services\LotImportService;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LotController extends Controller
{
    private function pusherInstance(): Pusher
    {
        return new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'useTLS'  => true,
            ]
        );
    }

    public function __construct(private LotRepository $repository)
    {
        $this->middleware('auth:sanctum');
        $this->middleware('permission:inventory.lots.view')->only(['index', 'show']);
        $this->middleware('permission:inventory.lots.store')->only(['store']);
        $this->middleware('permission:inventory.lots.update')->only(['update']);
        $this->middleware('permission:inventory.lots.destroy')->only(['destroy']);

        // Si usas políticas
        $this->authorizeResource(Lot::class, 'lot');
    }

    /** Listar lotes (paginado + filtros opcionales) */
    public function index(Request $request)
    {
        $lots = $this->repository->paginate($request->all());

        return LotResource::collection($lots);
    }

    /** Crear un lote */
    public function store(StoreLotRequest $request)
    {
        try {
            DB::beginTransaction();

            $lot = $this->repository->create($request->validated());

            // Registrar actividad
            $manzanaName = $lot->manzana->manzana_name ?? 'N/A';
            UserActivityLog::log(
                $request->user()->user_id,
                UserActivityLog::ACTION_LOT_ASSIGNED,
                "Lote #{$lot->lot_number} creado en manzana {$manzanaName}",
                [
                    'lot_id' => $lot->lot_id,
                    'lot_number' => $lot->lot_number,
                    'manzana_id' => $lot->manzana_id,
                ]
            );

            DB::commit();

            // Push evento "created"
            $pusher =  $this->pusherInstance();
            
            $pusher->trigger('lot-channel', 'created', [
                'lot' => (new LotResource($lot->load(['manzana', 'streetType', 'media'])))->toArray($request),
            ]);

            return (new LotResource($lot))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al crear lote',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Mostrar un lote */
    public function show(Lot $lot)
    {
        return new LotResource($lot->load(['manzana', 'streetType', 'media', 'financialTemplate']));
    }

    /** Actualizar un lote */
    public function update(UpdateLotRequest $request, Lot $lot)
    {
        try {
            DB::beginTransaction();

            $this->repository->update($lot, $request->validated());

            DB::commit();

            $pusher= $this->pusherInstance();
             
            $pusher->trigger('lot-channel', 'updated', [
                'lot' => (new LotResource($lot->fresh()->load(['manzana', 'streetType', 'media'])))->toArray($request),
            ]);

            return new LotResource($lot->fresh());
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error al actualizar lote',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** Eliminar un lote */
    public function destroy(Lot $lot)
    {
        try {
            $lotData = (new LotResource($lot->load(['manzana', 'streetType', 'media'])))->toArray(request());

            $this->repository->delete($lot);

            $pusher=$this->pusherInstance();
            $pusher->trigger('lot-channel', 'deleted', [
                'lot' => $lotData,
            ]);

            return response()->json(['message' => 'Lote eliminado correctamente']);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error al eliminar lote',
                'error'   => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Obtener catálogo público de lotes con información financiera
     */
    public function catalog(Request $request): JsonResponse
    {
        try {
            $lots = Lot::with([
                'manzana', 
                'streetType', 
                'financialTemplate'
            ])
            ->where('status', 'available')
            ->get()
            ->map(function ($lot) {
                $financingOptions = [];
                
                if ($lot->financialTemplate) {
                    $template = $lot->financialTemplate;
                    
                    if ($template->hasCashPrice()) {
                        $financingOptions['cash_price'] = $template->precio_contado;
                    }
                    
                    $installmentOptions = $template->getAvailableInstallmentOptions();
                    if (!empty($installmentOptions)) {
                        $financingOptions['installment_options'] = collect($installmentOptions)
                            ->map(function ($payment, $months) {
                                return [
                                    'months' => $months,
                                    'monthly_payment' => $payment
                                ];
                            })
                            ->values()
                            ->toArray();
                    }
                }
                
                return [
                    'lot_id' => $lot->lot_id,
                    'manzana' => $lot->manzana->name ?? '',
                    'num_lot' => $lot->num_lot,
                    'area_m2' => $lot->area_m2,
                    'street_type' => $lot->streetType->name ?? '',
                    'total_price' => $lot->total_price,
                    'status' => $lot->status,
                    'financing_options' => $financingOptions
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $lots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener catálogo de lotes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Simular financiamiento para un lote
     */
    public function financingSimulator(Request $request): JsonResponse
    {
        $request->validate([
            'lot_id' => 'required|exists:lots,lot_id',
            'financing_type' => 'required|in:cash,installments',
            'installment_months' => 'required_if:financing_type,installments|integer|in:24,40,44,55',
            'down_payment' => 'nullable|numeric|min:0'
        ]);
        
        try {
            $lot = Lot::with('financialTemplate')->findOrFail($request->lot_id);
            
            if (!$lot->financialTemplate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este lote no tiene información financiera disponible'
                ], 404);
            }
            
            $template = $lot->financialTemplate;
            $financingType = $request->financing_type;
            
            if ($financingType === 'cash') {
                if (!$template->hasCashPrice()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Este lote no está disponible para pago de contado'
                    ], 400);
                }
                
                $simulation = [
                    'lot_id' => $lot->lot_id,
                    'financing_type' => 'cash',
                    'total_price' => $template->precio_contado,
                    'down_payment' => $template->precio_contado,
                    'financing_amount' => 0,
                    'monthly_payment' => 0,
                    'total_payments' => 1
                ];
            } else {
                $months = $request->installment_months;
                $monthlyPaymentField = "installments_{$months}";
                
                if (!$template->$monthlyPaymentField || $template->$monthlyPaymentField <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Este lote no está disponible para financiamiento a {$months} meses"
                    ], 400);
                }
                
                $monthlyPayment = $template->$monthlyPaymentField;
                $downPayment = $request->down_payment ?? 0;
                $financingAmount = $template->precio_venta - $downPayment;
                
                $simulation = [
                    'lot_id' => $lot->lot_id,
                    'financing_type' => 'installments',
                    'total_price' => $template->precio_venta,
                    'down_payment' => $downPayment,
                    'financing_amount' => $financingAmount,
                    'monthly_payment' => $monthlyPayment,
                    'total_payments' => $months,
                    'balloon_payment' => $template->cuota_balon ?? 0
                ];
            }
            
            return response()->json([
                'success' => true,
                'data' => $simulation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al simular financiamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener template financiero de un lote específico
     */
    public function getFinancialTemplate(Lot $lot): JsonResponse
    {
        try {
            $template = $lot->financialTemplate;
            
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este lote no tiene información financiera disponible'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener template financiero',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener reglas de financiamiento por manzana
     */
    public function getManzanaFinancingRules(): JsonResponse
    {
        try {
            $rules = ManzanaFinancingRule::with('manzana')
                ->get()
                ->map(function ($rule) {
                    return [
                        'manzana' => $rule->manzana->name,
                        'financing_type' => $rule->financing_type,
                        'max_installments' => $rule->max_installments,
                        'cash_only' => $rule->cash_only,
                        'installment_options' => $rule->installment_options
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $rules
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener reglas de financiamiento',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Diagnóstico de templates financieros de lotes
     */
    public function diagnoseLotFinancialTemplates()
    {
        try {
            $lotImportService = new LotImportService();
            $diagnosis = $lotImportService->diagnoseLotFinancialTemplates();
            
            return response()->json([
                'success' => true,
                'diagnosis' => $diagnosis
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en diagnóstico: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Diagnóstico específico de la columna J desde Excel
     */
    public function diagnoseColumnJ(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls'
            ]);
            
            $file = $request->file('file');
            $lotImportService = new LotImportService();
            
            // Leer solo las primeras 2 filas del Excel para diagnóstico
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();
            
            if (count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'El archivo debe tener al menos 2 filas (headers y valores de financiamiento)'
                ], 400);
            }
            
            $headerRow = $rows[0];
            $financingValuesRow = $rows[1];
            
            $diagnosis = $lotImportService->diagnoseColumnJ($headerRow, $financingValuesRow);
            
            return response()->json([
                'success' => true,
                'message' => 'Diagnóstico de columna J completado',
                'data' => $diagnosis
            ]);
            
        } catch (Exception $e) {
            Log::error('[LotController] Error en diagnóstico de columna J', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar el diagnóstico: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Diagnóstico de reglas de financiamiento desde Excel
     */
    public function diagnoseFinancingRules(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls'
            ]);
            
            $file = $request->file('file');
            $lotImportService = new LotImportService();
            
            // Leer solo las primeras 2 filas del Excel para diagnóstico
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            
            $spreadsheet = $reader->load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Obtener headers (fila 1) y valores de financiamiento (fila 2)
            $headerRow = $worksheet->rangeToArray('A1:' . $worksheet->getHighestColumn() . '1')[0];
            $financingValuesRow = $worksheet->rangeToArray('A2:' . $worksheet->getHighestColumn() . '2')[0];
            
            // Ejecutar diagnóstico
            $diagnosis = $lotImportService->diagnoseFinancingRules($headerRow, $financingValuesRow);
            
            return response()->json([
                'success' => true,
                'diagnosis' => $diagnosis,
                'message' => 'Diagnóstico de reglas de financiamiento completado'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error en diagnóstico: ' . $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }
}
