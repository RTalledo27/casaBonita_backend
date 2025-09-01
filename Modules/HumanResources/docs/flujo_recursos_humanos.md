# 📋 FLUJO DE FUNCIONAMIENTO - MÓDULO RECURSOS HUMANOS

## 🎯 VISIÓN GENERAL DEL SISTEMA

```mermaid
graph TB
    A[👤 GESTIÓN DE EMPLEADOS] --> B[💰 SISTEMA DE COMISIONES]
    A --> C[🎁 SISTEMA DE BONOS]
    A --> D[💼 NÓMINAS]
    A --> E[👥 EQUIPOS DE TRABAJO]
    
    B --> F[📊 DASHBOARD ASESOR]
    C --> F
    D --> F
    E --> F
    
    B --> G[📈 DASHBOARD ADMIN]
    C --> G
    D --> G
    E --> G
```

## 🔄 FLUJO PRINCIPAL DE PROCESOS

### 1. 👤 GESTIÓN DE EMPLEADOS

```mermaid
flowchart TD
    A[Crear Usuario en Security] --> B[Crear Empleado en HR]
    B --> C{Tipo de Empleado?}
    
    C -->|Asesor Inmobiliario| D[✅ is_commission_eligible = true<br/>✅ is_bonus_eligible = true<br/>📊 commission_percentage = 5%]
    C -->|Vendedor| E[✅ is_commission_eligible = true<br/>✅ is_bonus_eligible = true<br/>📊 commission_percentage = 3%]
    C -->|Administrativo| F[❌ is_commission_eligible = false<br/>✅ is_bonus_eligible = true<br/>📊 commission_percentage = 0%]
    C -->|Gerente/Jefe| G[❌ is_commission_eligible = false<br/>✅ is_bonus_eligible = true<br/>📊 commission_percentage = 0%]
    
    D --> H[Asignar a Equipo]
    E --> H
    F --> H
    G --> H
    
    H --> I[Definir Meta Individual]
    I --> J[Empleado Activo]
```

### 2. 💰 SISTEMA DE COMISIONES

```mermaid
flowchart TD
    A[📝 Contrato Firmado en Sales] --> B{Empleado Elegible?}
    B -->|Sí| C[Calcular Comisión]
    B -->|No| D[Sin Comisión]
    
    C --> E{Plan de Pago?}
    E -->|Contado| F[100% del porcentaje]
    E -->|6 cuotas| G[90% del porcentaje]
    E -->|12 cuotas| H[80% del porcentaje]
    E -->|24 cuotas| I[70% del porcentaje]
    
    F --> J[Crear Registro Comisión]
    G --> J
    H --> J
    I --> J
    
    J --> K[Estado: Pendiente]
    K --> L[Proceso de Pago]
    L --> M[Estado: Pagado]
```

### 3. 🎁 SISTEMA DE BONOS

```mermaid
flowchart TD
    A[Fin de Mes] --> B[Procesar Bonos Automáticos]
    
    B --> C[Bono Meta Individual]
    B --> D[Bono Meta Equipo]
    B --> E[Bono Trimestral]
    B --> F[Bono Quincenal]
    B --> G[Bono Cobranza]
    
    C --> H{Cumplió Meta?}
    H -->|Sí| I[Calcular % Cumplimiento]
    H -->|No| J[Sin Bono]
    
    I --> K{Porcentaje?}
    K -->|100-110%| L[Bono Base]
    K -->|111-120%| M[Bono + 25%]
    K -->|>120%| N[Bono + 50%]
    
    L --> O[Crear Bono]
    M --> O
    N --> O
    
    O --> P{Requiere Aprobación?}
    P -->|Sí| Q[Estado: Pendiente Aprobación]
    P -->|No| R[Estado: Pendiente Pago]
    
    Q --> S[Aprobación Gerencia]
    S --> R
    R --> T[Proceso de Pago]
    T --> U[Estado: Pagado]
```

### 4. 💼 SISTEMA DE NÓMINAS

```mermaid
flowchart TD
    A[Fin de Período] --> B[Recopilar Datos]
    
    B --> C[Salario Base]
    B --> D[Comisiones del Período]
    B --> E[Bonos del Período]
    B --> F[Horas Extra]
    B --> G[Otros Ingresos]
    
    C --> H[Calcular Salario Bruto]
    D --> H
    E --> H
    F --> H
    G --> H
    
    H --> I[Calcular Deducciones]
    I --> J[Impuesto a la Renta]
    I --> K[Seguridad Social]
    I --> L[Seguro de Salud]
    I --> M[Otras Deducciones]
    
    J --> N[Calcular Salario Neto]
    K --> N
    L --> N
    M --> N
    
    N --> O[Generar Planilla]
    O --> P[Estado: Procesado]
    P --> Q[Aprobación]
    Q --> R[Estado: Aprobado]
    R --> S[Pago]
    S --> T[Estado: Pagado]
```

## 📊 DASHBOARDS Y REPORTES

### 🎯 Dashboard del Asesor

```mermaid
flowchart LR
    A[Asesor Login] --> B[Dashboard Personal]
    
    B --> C[📈 Ventas del Mes]
    B --> D[💰 Comisiones]
    B --> E[🎁 Bonos]
    B --> F[🎯 Meta vs Logrado]
    B --> G[📊 Ranking]
    
    C --> H[Contratos Firmados]
    C --> I[Monto Total Vendido]
    
    D --> J[Comisiones Pendientes]
    D --> K[Comisiones Pagadas]
    
    E --> L[Bonos del Mes]
    E --> M[Bonos Pendientes]
    
    F --> N[% de Cumplimiento]
    F --> O[Meta Individual]
    
    G --> P[Posición en Ranking]
    G --> Q[Top Performers]
```

### 👨‍💼 Dashboard del Administrador

```mermaid
flowchart LR
    A[Admin Login] --> B[Dashboard Administrativo]
    
    B --> C[📊 Resumen General]
    B --> D[👥 Gestión Empleados]
    B --> E[💰 Gestión Comisiones]
    B --> F[🎁 Gestión Bonos]
    B --> G[💼 Gestión Nóminas]
    
    C --> H[Total Empleados]
    C --> I[Ventas del Período]
    C --> J[Comisiones Totales]
    C --> K[Bonos Totales]
    
    D --> L[Empleados Activos]
    D --> M[Nuevos Empleados]
    D --> N[Performance por Equipo]
    
    E --> O[Comisiones Pendientes]
    E --> P[Procesar Pagos]
    E --> Q[Reportes de Comisiones]
    
    F --> R[Bonos Pendientes]
    F --> S[Aprobar Bonos]
    F --> T[Procesar Bonos]
    
    G --> U[Generar Nóminas]
    G --> V[Aprobar Nóminas]
    G --> W[Reportes de Nómina]
```

## 🔄 PROCESOS AUTOMÁTICOS

### ⏰ Tareas Programadas

```mermaid
gantt
    title Procesos Automáticos del Sistema HR
    dateFormat  DD
    axisFormat %d
    
    section Diario
    Actualizar Métricas    :active, daily1, 01, 1d
    Sincronizar Ventas     :active, daily2, 01, 1d
    
    section Semanal
    Reporte Performance    :weekly1, 07, 1d
    Backup Datos HR        :weekly2, 07, 1d
    
    section Quincenal
    Procesar Bonos Quincenales :biweekly1, 15, 1d
    
    section Mensual
    Procesar Comisiones    :monthly1, 30, 1d
    Procesar Bonos Mensuales :monthly2, 30, 1d
    Generar Nóminas        :monthly3, 30, 1d
    
    section Trimestral
    Bonos Trimestrales     :quarterly1, 90, 1d
    Evaluación Performance :quarterly2, 90, 1d
```

## 🎯 TIPOS DE EMPLEADOS Y CONFIGURACIONES

| Tipo de Empleado | Comisiones | Bonos | % Comisión | Meta Individual |
|------------------|------------|-------|------------|-----------------|
| **Asesor Inmobiliario** | ✅ Sí | ✅ Sí | 5% | ✅ Sí |
| **Vendedor** | ✅ Sí | ✅ Sí | 3% | ✅ Sí |
| **Administrativo** | ❌ No | ✅ Sí | 0% | ❌ No |
| **Gerente** | ❌ No | ✅ Sí | 0% | ❌ No |
| **Jefe de Ventas** | ❌ No | ✅ Sí | 0% | ❌ No |

## 🔗 INTEGRACIÓN CON OTROS MÓDULOS

```mermaid
graph TB
    HR[🏢 Human Resources] --> SEC[🔐 Security]
    HR --> SALES[💰 Sales]
    HR --> ACC[📊 Accounting]
    HR --> FIN[💼 Finance]
    HR --> COL[💳 Collections]
    
    SEC --> |Usuarios y Roles| HR
    SALES --> |Contratos y Ventas| HR
    HR --> |Comisiones| ACC
    HR --> |Nóminas| FIN
    COL --> |Bonos de Cobranza| HR
```

## 📈 MÉTRICAS CLAVE

- **Performance Individual**: Ventas vs Meta
- **Performance de Equipo**: Cumplimiento grupal
- **Comisiones**: Pendientes, pagadas, totales
- **Bonos**: Por tipo, por empleado, por período
- **Nóminas**: Costos totales, deducciones, neto
- **Ranking**: Top performers del mes/trimestre

---

*Este flujo representa el funcionamiento completo del módulo de Recursos Humanos, desde la gestión básica de empleados hasta los procesos automatizados de comisiones, bonos y nóminas.*