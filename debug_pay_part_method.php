<?php

/**
 * Endpoint de debugging para el proceso de pago de primera parte
 * Simula el proceso completo paso a paso con logging detallado
 */
public function debugPayPart($commissionId)
{
    Log::info('=== DEBUG PAY PART INICIADO ===', [
        'commission_id' => $commissionId,
        'timestamp' => now()->toDateTimeString()
    ]);
    
    try {
        // Paso 1: Buscar la comisión
        $commission = Commission::where('commission_id', $commissionId)->first();
        
        if (!$commission) {
            return response()->json([
                'success' => false,
                'error' => 'Comisión no encontrada',
                'commission_id' => $commissionId
            ], 404);
        }
        
        Log::info('DEBUG: Comisión encontrada', [
            'commission_id' => $commission->commission_id,
            'status' => $commission->status,
            'payment_part' => $commission->payment_part,
            'is_eligible' => $commission->is_eligible_for_payment,
            'requires_verification' => $commission->requires_client_payment_verification
        ]);
        
        // Paso 2: Validaciones básicas
        $validationResults = [
            'commission_exists' => true,
            'current_status' => $commission->status,
            'payment_part' => $commission->payment_part,
            'is_eligible' => $commission->is_eligible_for_payment,
            'requires_verification' => $commission->requires_client_payment_verification
        ];
        
        // Verificar si la comisión está en estado correcto para pago
        if (!in_array($commission->status, ['generated', 'approved'])) {
            return response()->json([
                'success' => false,
                'error' => 'La comisión debe estar en estado generated o approved para poder ser pagada',
                'validation_results' => $validationResults
            ], 400);
        }
        
        // Paso 3: Simular el proceso de pago sin ejecutar servicios reales
        $paymentSimulation = [
            'step_1_validation' => [
                'payment_part_expected' => 1,
                'commission_payment_part' => $commission->payment_part,
                'validation_passed' => ($commission->payment_part == 1)
            ],
            'step_2_status_check' => [
                'current_status' => $commission->status,
                'can_proceed' => !in_array($commission->status, ['partially_paid', 'fully_paid'])
            ],
            'step_3_eligibility' => [
                'is_eligible' => $commission->is_eligible_for_payment,
                'requires_verification' => $commission->requires_client_payment_verification
            ]
        ];
        
        // Paso 4: Verificar si requiere verificación de pagos del cliente
        if ($commission->requires_client_payment_verification) {
            $paymentSimulation['step_4_client_verification'] = [
                'required' => true,
                'current_verification_status' => $commission->payment_verification_status,
                'note' => 'Este paso requeriría verificar pagos del cliente en el sistema real'
            ];
        } else {
            $paymentSimulation['step_4_client_verification'] = [
                'required' => false,
                'note' => 'No requiere verificación de pagos del cliente'
            ];
        }
        
        // Paso 5: Simular el resultado final
        $paymentSimulation['step_5_final_result'] = [
            'would_update_status_to' => 'partially_paid',
            'would_update_payment_part_to' => 2,
            'would_maintain_eligibility' => true,
            'simulation_only' => true
        ];
        
        return response()->json([
            'success' => true,
            'message' => 'Simulación del proceso de pago completada exitosamente',
            'commission_id' => $commission->commission_id,
            'validation_results' => $validationResults,
            'payment_simulation' => $paymentSimulation,
            'summary' => [
                'commission_ready_for_payment' => (
                    $commission->payment_part == 1 && 
                    in_array($commission->status, ['generated', 'approved']) &&
                    $commission->is_eligible_for_payment
                ),
                'blocking_issues' => [],
                'next_steps' => 'La comisión está lista para el proceso de pago real'
            ]
        ]);
        
    } catch (\Exception $e) {
        Log::error('ERROR en debugPayPart', [
            'commission_id' => $commissionId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Error interno: ' . $e->getMessage(),
            'commission_id' => $commissionId
        ], 500);
    }
}