<?php

namespace Modules\HumanResources\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\HumanResources\Models\TaxParameter;
use Illuminate\Routing\Controller;

class TaxParameterController extends Controller
{
    /**
     * Obtener parámetros de un año específico
     */
    public function getByYear(int $year): JsonResponse
    {
        try {
            $params = TaxParameter::where('year', $year)
                ->where('is_active', true)
                ->firstOrFail();
            
            return response()->json([
                'success' => true,
                'data' => $params
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros no encontrados para el año ' . $year
            ], 404);
        }
    }
    
    /**
     * Obtener parámetros del año actual
     */
    public function getCurrent(): JsonResponse
    {
        try {
            $params = TaxParameter::getCurrent();
            
            return response()->json([
                'success' => true,
                'data' => $params
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontraron parámetros activos'
            ], 404);
        }
    }
    
    /**
     * Listar todos los años con parámetros
     */
    public function index(): JsonResponse
    {
        $params = TaxParameter::orderBy('year', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $params
        ]);
    }
    
    /**
     * Actualizar parámetros de un año
     */
    public function update(Request $request, int $year): JsonResponse
    {
        $validated = $request->validate([
            'uit_amount' => 'required|numeric|min:0',
            'family_allowance' => 'required|numeric|min:0',
            'minimum_wage' => 'required|numeric|min:0',
            'afp_contribution_rate' => 'required|numeric|min:0|max:100',
            'afp_insurance_rate' => 'required|numeric|min:0|max:100',
            'afp_prima_commission' => 'required|numeric|min:0|max:100',
            'afp_integra_commission' => 'required|numeric|min:0|max:100',
            'afp_profuturo_commission' => 'required|numeric|min:0|max:100',
            'afp_habitat_commission' => 'required|numeric|min:0|max:100',
            'onp_rate' => 'required|numeric|min:0|max:100',
            'essalud_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_deduction_uit' => 'required|numeric|min:0',
            'rent_tax_tramo1_uit' => 'required|numeric|min:0',
            'rent_tax_tramo1_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_tramo2_uit' => 'required|numeric|min:0',
            'rent_tax_tramo2_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_tramo3_uit' => 'required|numeric|min:0',
            'rent_tax_tramo3_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_tramo4_uit' => 'required|numeric|min:0',
            'rent_tax_tramo4_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_tramo5_rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean'
        ]);
        
        try {
            $params = TaxParameter::where('year', $year)->firstOrFail();
            $params->update($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Parámetros actualizados exitosamente',
                'data' => $params
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar parámetros: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Crear parámetros para un nuevo año
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|unique:tax_parameters,year',
            'uit_amount' => 'required|numeric|min:0',
            'family_allowance' => 'required|numeric|min:0',
            'minimum_wage' => 'required|numeric|min:0',
            'afp_contribution_rate' => 'required|numeric|min:0|max:100',
            'afp_insurance_rate' => 'required|numeric|min:0|max:100',
            'afp_prima_commission' => 'required|numeric|min:0|max:100',
            'afp_integra_commission' => 'required|numeric|min:0|max:100',
            'afp_profuturo_commission' => 'required|numeric|min:0|max:100',
            'afp_habitat_commission' => 'required|numeric|min:0|max:100',
            'onp_rate' => 'required|numeric|min:0|max:100',
            'essalud_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_deduction_uit' => 'required|numeric|min:0',
            'rent_tax_tramo1_uit' => 'required|numeric|min:0',
            'rent_tax_tramo1_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_tramo2_uit' => 'required|numeric|min:0',
            'rent_tax_tramo2_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_tramo3_uit' => 'required|numeric|min:0',
            'rent_tax_tramo3_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_tramo4_uit' => 'required|numeric|min:0',
            'rent_tax_tramo4_rate' => 'required|numeric|min:0|max:100',
            'rent_tax_tramo5_rate' => 'required|numeric|min:0|max:100',
            'is_active' => 'sometimes|boolean'
        ]);
        
        try {
            $params = TaxParameter::create($validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Parámetros creados exitosamente',
                'data' => $params
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear parámetros: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Copiar parámetros de un año a otro
     */
    public function copyYear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_year' => 'required|integer|exists:tax_parameters,year',
            'to_year' => 'required|integer|unique:tax_parameters,year'
        ]);
        
        try {
            $sourceParams = TaxParameter::where('year', $validated['from_year'])->firstOrFail();
            $newParams = $sourceParams->replicate();
            $newParams->year = $validated['to_year'];
            $newParams->save();
            
            return response()->json([
                'success' => true,
                'message' => "Parámetros copiados de {$validated['from_year']} a {$validated['to_year']}",
                'data' => $newParams
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al copiar parámetros: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calcular asignación familiar automáticamente (10% RMV)
     */
    public function calculateFamilyAllowance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'minimum_wage' => 'required|numeric|min:0'
        ]);
        
        $familyAllowance = $validated['minimum_wage'] * 0.10;
        
        return response()->json([
            'success' => true,
            'data' => [
                'minimum_wage' => $validated['minimum_wage'],
                'family_allowance' => round($familyAllowance, 2)
            ]
        ]);
    }
}
