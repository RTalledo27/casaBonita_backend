<?php

/**
 * FLUJO COMPLETO: PAGO DE CUOTAS Y RELACIÓN CON COMISIONES
 * 
 * Este script documenta el flujo completo desde que se paga una cuota
 * en gestión de cuotas hasta que se activan las comisiones correspondientes.
 */

require_once __DIR__ . '/vendor/autoload.php';

echo "\n=== FLUJO COMPLETO: PAGO DE CUOTAS Y COMISIONES ===\n\n";

echo "1. GESTIÓN DE CUOTAS (Frontend)\n";
echo "   - El usuario marca una cuota como pagada en el dashboard de cobranzas\n";
echo "   - Componente: collections-simplified-dashboard.component.ts\n";
echo "   - Servicio: collections-simplified.service.ts -> markPaymentPaid()\n";
echo "   - Endpoint: PATCH /sales/schedules/{id}/mark-paid\n\n";

echo "2. CONTROLADOR DE CRONOGRAMA DE PAGOS (Backend)\n";
echo "   - PaymentScheduleController::markAsPaid()\n";
echo "   - Actualiza el PaymentSchedule con status='pagado'\n";
echo "   - Campos actualizados: payment_date, amount_paid, payment_method, notes\n\n";

echo "3. CREACIÓN DE PAYMENT (Sales Module)\n";
echo "   - Se crea un registro en la tabla 'payments' del módulo Sales\n";
echo "   - Modelo: Modules\\Sales\\Models\\Payment\n";
echo "   - Relación: belongsTo PaymentSchedule\n\n";

echo "4. SINCRONIZACIÓN AUTOMÁTICA (Payment Model Observer)\n";
echo "   - Payment::boot() -> static::created() -> syncWithCollections()\n";
echo "   - Busca la AccountReceivable correspondiente al contrato\n";
echo "   - Crea automáticamente un CustomerPayment en el módulo Collections\n\n";

echo "5. CUSTOMER PAYMENT CREADO (Collections Module)\n";
echo "   - Se crea registro en 'customer_payments'\n";
echo "   - Modelo: Modules\\Collections\\Models\\CustomerPayment\n";
echo "   - Campos: client_id, ar_id, payment_date, amount, etc.\n\n";

echo "6. DETECCIÓN DE TIPO DE CUOTA\n";
echo "   - PaymentDetectionService::detectInstallmentType()\n";
echo "   - Determina si es 'first', 'second' o 'regular'\n";
echo "   - Basado en el número de pagos previos del contrato\n\n";

echo "7. VALIDACIÓN DE CRITERIOS\n";
echo "   - PaymentDetectionService::validatePaymentCriteria()\n";
echo "   - Verifica monto mínimo (90% de la cuota)\n";
echo "   - Verifica tolerancia de fechas (5 días después del vencimiento)\n\n";

echo "8. DISPARO DE EVENTO (Si cumple criterios)\n";
echo "   - Se crea InstallmentPaidEvent\n";
echo "   - Event::dispatch() dispara el evento\n";
echo "   - Se actualiza commission_event_dispatched = true\n\n";

echo "9. LISTENER DE VERIFICACIÓN DE COMISIONES\n";
echo "   - CommissionVerificationListener::handle()\n";
echo "   - Procesa el evento InstallmentPaidEvent\n";
echo "   - Busca comisiones relacionadas al contrato\n\n";

echo "10. VERIFICACIÓN DE COMISIONES\n";
echo "    - CommissionVerificationService::processCommissionVerification()\n";
echo "    - Para cada comisión del contrato:\n";
echo "      * Verifica si el pago afecta la comisión (payment_part)\n";
echo "      * payment_part = 1 -> solo afectada por 'first' installment\n";
echo "      * payment_part = 2 -> solo afectada por 'second' installment\n";
echo "      * Actualiza verification_status a 'verified'\n";
echo "      * Actualiza verification_date\n\n";

echo "11. ACTIVACIÓN DE COMISIONES\n";
echo "    - Las comisiones verificadas cambian su estado\n";
echo "    - Se pueden pagar a través del sistema de comisiones\n";
echo "    - CommissionController::payCommissionPart()\n\n";

echo "=== TABLAS INVOLUCRADAS ===\n\n";
echo "• payment_schedules (Sales) - Cronograma de pagos\n";
echo "• payments (Sales) - Pagos realizados\n";
echo "• customer_payments (Collections) - Pagos de clientes\n";
echo "• accounts_receivable (Collections) - Cuentas por cobrar\n";
echo "• commissions (HR) - Comisiones de empleados\n";
echo "• commission_verifications (HR) - Verificaciones de comisiones\n";
echo "• payment_events (Global) - Eventos de pagos\n\n";

echo "=== CAMPOS CLAVE ===\n\n";
echo "PaymentSchedule:\n";
echo "• status: 'pendiente' -> 'pagado'\n";
echo "• payment_date, amount_paid, payment_method\n\n";

echo "CustomerPayment:\n";
echo "• installment_type: 'first', 'second', 'regular'\n";
echo "• affects_commissions: true/false\n";
echo "• commission_event_dispatched: true/false\n\n";

echo "Commission:\n";
echo "• payment_part: 1 (primera cuota), 2 (segunda cuota)\n";
echo "• verification_status: 'pending' -> 'verified'\n";
echo "• payment_status: 'pendiente' -> 'pagado'\n\n";

echo "=== FLUJO RESUMIDO ===\n\n";
echo "Cuota Pagada (Frontend) \n";
echo "    ↓\n";
echo "PaymentSchedule.status = 'pagado' \n";
echo "    ↓\n";
echo "Payment creado (Sales) \n";
echo "    ↓\n";
echo "CustomerPayment creado (Collections) \n";
echo "    ↓\n";
echo "InstallmentPaidEvent disparado \n";
echo "    ↓\n";
echo "CommissionVerificationListener \n";
echo "    ↓\n";
echo "Comisiones verificadas y activadas \n\n";

echo "=== PUNTOS IMPORTANTES ===\n\n";
echo "1. La sincronización es AUTOMÁTICA a través del observer del modelo Payment\n";
echo "2. Solo los pagos 'first' y 'second' afectan comisiones\n";
echo "3. Las comisiones tienen payment_part para dividir por cuotas\n";
echo "4. Se valida monto mínimo y tolerancia de fechas\n";
echo "5. El evento se dispara solo si cumple todos los criterios\n";
echo "6. Cada comisión se verifica individualmente según su payment_part\n\n";

echo "Script completado.\n";