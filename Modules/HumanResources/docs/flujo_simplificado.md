# 🔄 FLUJO SIMPLIFICADO - RECURSOS HUMANOS

## 📋 PROCESO PRINCIPAL

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   👤 EMPLEADO   │    │  💰 COMISIONES  │    │   🎁 BONOS      │
│                 │    │                 │    │                 │
│ • Crear perfil  │    │ • Auto-cálculo  │    │ • Meta mensual  │
│ • Asignar tipo  │───▶│ • Por contrato  │───▶│ • Meta equipo   │
│ • Definir meta  │    │ • Según plan    │    │ • Trimestral    │
│ • Activar       │    │ • Estado: pend. │    │ • Quincenal     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│  💼 NÓMINAS     │    │  📊 DASHBOARD   │    │  📈 REPORTES    │
│                 │    │                 │    │                 │
│ • Salario base  │    │ • Vista asesor  │    │ • Performance   │
│ • + Comisiones  │◀───│ • Vista admin   │───▶│ • Rankings      │
│ • + Bonos       │    │ • Métricas      │    │ • Estadísticas  │
│ • - Deducciones │    │ • Alertas       │    │ • Tendencias    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 🎯 TIPOS DE EMPLEADOS

```
ASESOR INMOBILIARIO          VENDEDOR               ADMINISTRATIVO
┌─────────────────┐         ┌─────────────────┐    ┌─────────────────┐
│ ✅ Comisiones   │         │ ✅ Comisiones   │    │ ❌ Comisiones   │
│ ✅ Bonos        │         │ ✅ Bonos        │    │ ✅ Bonos        │
│ 📊 5% comisión  │         │ 📊 3% comisión  │    │ 📊 0% comisión  │
│ 🎯 Meta indiv.  │         │ 🎯 Meta indiv.  │    │ 🎯 Sin meta     │
└─────────────────┘         └─────────────────┘    └─────────────────┘

GERENTE                      JEFE DE VENTAS
┌─────────────────┐         ┌─────────────────┐
│ ❌ Comisiones   │         │ ❌ Comisiones   │
│ ✅ Bonos        │         │ ✅ Bonos        │
│ 📊 0% comisión  │         │ 📊 0% comisión  │
│ 🎯 Sin meta     │         │ 🎯 Sin meta     │
└─────────────────┘         └─────────────────┘
```

## 💰 CÁLCULO DE COMISIONES

```
CONTRATO FIRMADO
       │
       ▼
¿Es asesor/vendedor? ──NO──▶ Sin comisión
       │
      SÍ
       │
       ▼
PLAN DE PAGO:
• Contado     → 100% del %
• 6 cuotas    → 90% del %
• 12 cuotas   → 80% del %
• 24 cuotas   → 70% del %
       │
       ▼
COMISIÓN = Precio × % × Multiplicador
       │
       ▼
Estado: PENDIENTE → PAGADO
```

## 🎁 SISTEMA DE BONOS

```
FIN DE MES
    │
    ▼
VERIFICAR CUMPLIMIENTO DE META
    │
    ├─ 100-110% → Bono base
    ├─ 111-120% → Bono + 25%
    └─ >120%    → Bono + 50%
    │
    ▼
¿Requiere aprobación?
    │
    ├─ SÍ → PENDIENTE APROBACIÓN → APROBADO
    └─ NO → PENDIENTE PAGO
    │
    ▼
PAGADO
```

## 📊 DASHBOARD ASESOR

```
┌─────────────────────────────────────────────────────────────┐
│                    🎯 MI DASHBOARD                          │
├─────────────────────────────────────────────────────────────┤
│ 📈 VENTAS ESTE MES                                          │
│ • Contratos: 3                                              │
│ • Monto total: $150,000                                     │
│ • Meta: $200,000 (75% cumplido)                            │
├─────────────────────────────────────────────────────────────┤
│ 💰 COMISIONES                                               │
│ • Pendientes: $7,500                                        │
│ • Pagadas: $15,000                                          │
├─────────────────────────────────────────────────────────────┤
│ 🎁 BONOS                                                    │
│ • Este mes: $2,000                                          │
│ • Pendientes: $1,500                                        │
├─────────────────────────────────────────────────────────────┤
│ 📊 RANKING                                                  │
│ • Posición: #3 de 8 asesores                               │
│ • Top performer: Juan Pérez ($250,000)                     │
└─────────────────────────────────────────────────────────────┘
```

## 👨‍💼 DASHBOARD ADMIN

```
┌─────────────────────────────────────────────────────────────┐
│                  📊 DASHBOARD ADMINISTRATIVO                │
├─────────────────────────────────────────────────────────────┤
│ 👥 EMPLEADOS                                                │
│ • Total activos: 15                                         │
│ • Asesores: 8                                               │
│ • Administrativos: 7                                        │
├─────────────────────────────────────────────────────────────┤
│ 💰 COMISIONES (Este mes)                                    │
│ • Total: $45,000                                            │
│ • Pendientes: $25,000                                       │
│ • Pagadas: $20,000                                          │
├─────────────────────────────────────────────────────────────┤
│ 🎁 BONOS (Este mes)                                         │
│ • Total: $15,000                                            │
│ • Pendientes aprobación: $8,000                            │
│ • Pendientes pago: $4,000                                   │
│ • Pagados: $3,000                                           │
├─────────────────────────────────────────────────────────────┤
│ 📈 TOP PERFORMERS                                           │
│ 1. Juan Pérez - $250,000                                    │
│ 2. María García - $220,000                                  │
│ 3. Carlos López - $180,000                                  │
└─────────────────────────────────────────────────────────────┘
```

## ⚙️ PROCESOS AUTOMÁTICOS

```
DIARIO:
• Sincronizar ventas con Sales
• Actualizar métricas de performance
• Verificar metas individuales

QUINCENAL:
• Procesar bonos quincenales
• Generar reportes de performance

MENSUAL:
• Calcular comisiones automáticas
• Procesar bonos mensuales
• Generar nóminas
• Actualizar rankings

TRIMESTRAL:
• Procesar bonos trimestrales
• Evaluación de performance anual
• Revisión de metas y objetivos
```

---

**🎯 RESUMEN**: El módulo de Recursos Humanos gestiona empleados, calcula automáticamente comisiones basadas en ventas, procesa bonos por cumplimiento de metas, genera nóminas completas y proporciona dashboards detallados tanto para asesores como para administradores.