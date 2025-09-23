# DIAGRAMA DE FLUJO: CUOTAS → COMISIONES DEL ASESOR

## Flujo Visual del Sistema Casa Bonita

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           MÓDULO DE COBRANZA                               │
│                        (Gestión de Cuotas)                                 │
└─────────────────────────┬───────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  USUARIO PAGA CUOTA #1 DEL CONTRATO CON20257868                            │
│  ✓ payment_schedules.status = 'PAID'                                       │
│  ✓ installment_number = 1                                                  │
│  ✓ Se crea registro en tabla 'payments'                                    │
└─────────────────────────┬───────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                    EVENTO AUTOMÁTICO                                       │
│  InstallmentPaidEvent se dispara                                           │
│  PaymentScheduleObserver detecta el cambio                                 │
└─────────────────────────┬───────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│              BACKEND - VERIFICACIÓN AUTOMÁTICA                             │
│  CommissionPaymentVerificationService.php                                  │
│  ✓ Identifica contrato CON20257868                                         │
│  ✓ Busca comisiones del asesor EMP6303                                     │
│  ✓ Verifica payment_part = 1                                               │
│  ✓ Habilita primera parte de comisión                                      │
└─────────────────────────┬───────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                   MÓDULO DE RECURSOS HUMANOS                               │
│              (Apartado de Comisiones del Asesor)                           │
│                                                                             │
│  advisor-commissions-modal.component.ts                                    │
│  ┌─────────────────────────────────────────────────────────┐               │
│  │  CONTRATO: CON20257868                                  │               │
│  │  ASESOR: EMP6303                                        │               │
│  │                                                         │               │
│  │  ┌─────────────────┐  ┌─────────────────┐              │               │
│  │  │ PRIMERA PARTE   │  │ SEGUNDA PARTE   │              │               │
│  │  │ ✅ DISPONIBLE   │  │ ❌ BLOQUEADA    │              │               │
│  │  │ [Pagar Parte 1] │  │ [Bloqueado]     │              │               │
│  │  └─────────────────┘  └─────────────────┘              │               │
│  └─────────────────────────────────────────────────────────┘               │
└─────────────────────────┬───────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  ASESOR HACE CLIC EN "PAGAR PRIMERA PARTE"                                 │
│  ✓ commissions.first_payment_date = NOW()                                  │
│  ✓ commissions.status = 'PARTIALLY_PAID'                                   │
│  ✓ Se registra el pago del 50% de la comisión                              │
└─────────────────────────┬───────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  USUARIO PAGA CUOTA #2 DEL CONTRATO CON20257868                            │
│  ✓ payment_schedules.status = 'PAID'                                       │
│  ✓ installment_number = 2                                                  │
│  ✓ Mismo proceso automático se repite                                      │
└─────────────────────────┬───────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│              INTERFAZ ACTUALIZADA AUTOMÁTICAMENTE                          │
│                                                                             │
│  ┌─────────────────────────────────────────────────────────┐               │
│  │  CONTRATO: CON20257868                                  │               │
│  │  ASESOR: EMP6303                                        │               │
│  │                                                         │               │
│  │  ┌─────────────────┐  ┌─────────────────┐              │               │
│  │  │ PRIMERA PARTE   │  │ SEGUNDA PARTE   │              │               │
│  │  │ ✅ PAGADA       │  │ ✅ DISPONIBLE   │              │               │
│  │  │ [Completada]    │  │ [Pagar Parte 2] │              │               │
│  │  └─────────────────┘  └─────────────────┘              │               │
│  └─────────────────────────────────────────────────────────┘               │
└─────────────────────────┬───────────────────────────────────────────────────┘
                          │
                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│  ASESOR HACE CLIC EN "PAGAR SEGUNDA PARTE"                                 │
│  ✓ commissions.second_payment_date = NOW()                                 │
│  ✓ commissions.status = 'PAID'                                             │
│  ✓ Comisión completamente pagada (100%)                                    │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Puntos Clave de la Integración

### 🔄 Sincronización Automática
- **Tiempo Real**: Los cambios se reflejan inmediatamente
- **Sin Intervención Manual**: El sistema detecta automáticamente los pagos
- **Consistencia**: Ambos módulos siempre están sincronizados

### 🔒 Validaciones de Seguridad
- **Orden Secuencial**: No se puede pagar la segunda parte sin la primera
- **Verificación de Cuotas**: Solo se habilitan comisiones si las cuotas están pagadas
- **Estados Consistentes**: La interfaz refleja el estado real de la base de datos

### 📊 Mapeo Directo
```
Cuota #1 Pagada → payment_part = 1 → Primera Parte Comisión (50%)
Cuota #2 Pagada → payment_part = 2 → Segunda Parte Comisión (50%)
```

### 🎯 Ejemplo Práctico
**Contrato**: CON20257868  
**Asesor**: EMP6303  
**Resultado**: Control total del flujo de pagos desde cuotas hasta comisiones

---

*Este diagrama muestra cómo Casa Bonita garantiza que los asesores solo cobren sus comisiones cuando los clientes cumplan con sus obligaciones de pago.*