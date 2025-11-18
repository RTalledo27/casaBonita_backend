# 🎛️ API de Parámetros Tributarios - Gestión Dinámica

**Fecha:** 14 de Noviembre de 2025  
**Objetivo:** Permitir actualización de parámetros tributarios sin modificar código

---

## ✅ **VENTAJAS DEL SISTEMA DINÁMICO**

1. **Actualización sin código:** RR.HH. puede actualizar valores desde el frontend
2. **Historial por año:** Mantiene valores de años anteriores para reportes
3. **Fácil migración:** Copiar parámetros de un año a otro
4. **Cálculos automáticos:** Asignación familiar se calcula desde RMV
5. **Sin deployments:** No requiere redeplegar el sistema

---

## 📡 **ENDPOINTS DISPONIBLES**

### **1. Obtener parámetros del año actual**
```http
GET /api/v1/hr/tax-parameters/current
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "parameter_id": 1,
    "year": 2025,
    "uit_amount": "5350.00",
    "family_allowance": "113.00",
    "minimum_wage": "1130.00",
    "afp_contribution_rate": "10.00",
    "afp_insurance_rate": "0.99",
    "afp_prima_commission": "1.47",
    "afp_integra_commission": "1.00",
    "afp_profuturo_commission": "1.20",
    "afp_habitat_commission": "1.00",
    "onp_rate": "13.00",
    "essalud_rate": "9.00",
    "rent_tax_deduction_uit": "7.00",
    "rent_tax_tramo1_uit": "5.00",
    "rent_tax_tramo1_rate": "8.00",
    "rent_tax_tramo2_uit": "20.00",
    "rent_tax_tramo2_rate": "14.00",
    "rent_tax_tramo3_uit": "35.00",
    "rent_tax_tramo3_rate": "17.00",
    "rent_tax_tramo4_uit": "45.00",
    "rent_tax_tramo4_rate": "20.00",
    "rent_tax_tramo5_rate": "30.00",
    "is_active": true,
    "created_at": "2025-11-14T...",
    "updated_at": "2025-11-14T..."
  }
}
```

---

### **2. Obtener parámetros de un año específico**
```http
GET /api/v1/hr/tax-parameters/{year}
```

**Ejemplo:**
```http
GET /api/v1/hr/tax-parameters/2025
```

---

### **3. Listar todos los años**
```http
GET /api/v1/hr/tax-parameters
```

**Respuesta:**
```json
{
  "success": true,
  "data": [
    {
      "parameter_id": 2,
      "year": 2026,
      "uit_amount": "5500.00",
      ...
    },
    {
      "parameter_id": 1,
      "year": 2025,
      "uit_amount": "5350.00",
      ...
    },
    {
      "parameter_id": 3,
      "year": 2024,
      "uit_amount": "5050.00",
      ...
    }
  ]
}
```

---

### **4. Actualizar parámetros de un año**
```http
PUT /api/v1/hr/tax-parameters/{year}
Content-Type: application/json
```

**Body:**
```json
{
  "uit_amount": 5500.00,
  "family_allowance": 120.00,
  "minimum_wage": 1200.00,
  "afp_contribution_rate": 10.00,
  "afp_insurance_rate": 0.99,
  "afp_prima_commission": 1.50,
  "afp_integra_commission": 1.05,
  "afp_profuturo_commission": 1.25,
  "afp_habitat_commission": 1.05,
  "onp_rate": 13.00,
  "essalud_rate": 9.00,
  "rent_tax_deduction_uit": 7.00,
  "rent_tax_tramo1_uit": 5.00,
  "rent_tax_tramo1_rate": 8.00,
  "rent_tax_tramo2_uit": 20.00,
  "rent_tax_tramo2_rate": 14.00,
  "rent_tax_tramo3_uit": 35.00,
  "rent_tax_tramo3_rate": 17.00,
  "rent_tax_tramo4_uit": 45.00,
  "rent_tax_tramo4_rate": 20.00,
  "rent_tax_tramo5_rate": 30.00
}
```

**Respuesta:**
```json
{
  "success": true,
  "message": "Parámetros actualizados exitosamente",
  "data": { ... }
}
```

---

### **5. Crear parámetros para un nuevo año**
```http
POST /api/v1/hr/tax-parameters
Content-Type: application/json
```

**Body:**
```json
{
  "year": 2026,
  "uit_amount": 5500.00,
  "family_allowance": 120.00,
  "minimum_wage": 1200.00,
  ...
}
```

---

### **6. Copiar parámetros de un año a otro**
```http
POST /api/v1/hr/tax-parameters/copy-year
Content-Type: application/json
```

**Body:**
```json
{
  "from_year": 2025,
  "to_year": 2026
}
```

**Uso:** Al llegar diciembre 2025, puedes copiar los parámetros de 2025 a 2026 y luego ajustar solo lo que cambió (por ejemplo, UIT y RMV).

**Respuesta:**
```json
{
  "success": true,
  "message": "Parámetros copiados de 2025 a 2026",
  "data": { ... }
}
```

---

### **7. Calcular asignación familiar automáticamente**
```http
POST /api/v1/hr/tax-parameters/calculate-family-allowance
Content-Type: application/json
```

**Body:**
```json
{
  "minimum_wage": 1130.00
}
```

**Respuesta:**
```json
{
  "success": true,
  "data": {
    "minimum_wage": 1130.00,
    "family_allowance": 113.00
  }
}
```

**Uso:** Cuando actualizas RMV, usa este endpoint para calcular automáticamente la asignación familiar (10%).

---

## 🖥️ **USO EN EL FRONTEND**

### **Componente de Administración de Parámetros**

Puedes crear un componente Angular para que RR.HH. actualice estos valores:

```typescript
// tax-parameters.component.ts
import { Component, OnInit } from '@angular/core';
import { TaxParameterService } from '../../services/tax-parameter.service';

@Component({
  selector: 'app-tax-parameters',
  template: `
    <div class="container">
      <h2>Parámetros Tributarios {{ selectedYear }}</h2>
      
      <div class="year-selector">
        <button *ngFor="let year of years" 
                (click)="loadYear(year)"
                [class.active]="year === selectedYear">
          {{ year }}
        </button>
        <button (click)="createNewYear()">+ Nuevo Año</button>
      </div>
      
      <form [formGroup]="form" (ngSubmit)="save()">
        <h3>Valores Base</h3>
        <div class="form-group">
          <label>UIT {{ selectedYear }}</label>
          <input type="number" formControlName="uit_amount" step="0.01">
        </div>
        
        <div class="form-group">
          <label>RMV {{ selectedYear }}</label>
          <input type="number" formControlName="minimum_wage" 
                 step="0.01" (blur)="calculateFamilyAllowance()">
        </div>
        
        <div class="form-group">
          <label>Asignación Familiar (10% RMV)</label>
          <input type="number" formControlName="family_allowance" 
                 step="0.01" readonly>
        </div>
        
        <h3>AFP</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Aporte (%)</label>
            <input type="number" formControlName="afp_contribution_rate" step="0.01">
          </div>
          <div class="form-group">
            <label>Seguro (%)</label>
            <input type="number" formControlName="afp_insurance_rate" step="0.01">
          </div>
        </div>
        
        <div class="form-row">
          <div class="form-group">
            <label>Comisión Prima (%)</label>
            <input type="number" formControlName="afp_prima_commission" step="0.01">
          </div>
          <div class="form-group">
            <label>Comisión Integra (%)</label>
            <input type="number" formControlName="afp_integra_commission" step="0.01">
          </div>
          <div class="form-group">
            <label>Comisión Profuturo (%)</label>
            <input type="number" formControlName="afp_profuturo_commission" step="0.01">
          </div>
          <div class="form-group">
            <label>Comisión Habitat (%)</label>
            <input type="number" formControlName="afp_habitat_commission" step="0.01">
          </div>
        </div>
        
        <h3>ONP y EsSalud</h3>
        <div class="form-row">
          <div class="form-group">
            <label>Tasa ONP (%)</label>
            <input type="number" formControlName="onp_rate" step="0.01">
          </div>
          <div class="form-group">
            <label>Tasa EsSalud (%)</label>
            <input type="number" formControlName="essalud_rate" step="0.01">
          </div>
        </div>
        
        <h3>Impuesto a la Renta</h3>
        <div class="form-group">
          <label>Deducción (UIT)</label>
          <input type="number" formControlName="rent_tax_deduction_uit" step="0.01">
        </div>
        
        <table class="tax-brackets">
          <thead>
            <tr>
              <th>Tramo</th>
              <th>Hasta (UIT)</th>
              <th>Tasa (%)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>1</td>
              <td><input type="number" formControlName="rent_tax_tramo1_uit"></td>
              <td><input type="number" formControlName="rent_tax_tramo1_rate"></td>
            </tr>
            <tr>
              <td>2</td>
              <td><input type="number" formControlName="rent_tax_tramo2_uit"></td>
              <td><input type="number" formControlName="rent_tax_tramo2_rate"></td>
            </tr>
            <tr>
              <td>3</td>
              <td><input type="number" formControlName="rent_tax_tramo3_uit"></td>
              <td><input type="number" formControlName="rent_tax_tramo3_rate"></td>
            </tr>
            <tr>
              <td>4</td>
              <td><input type="number" formControlName="rent_tax_tramo4_uit"></td>
              <td><input type="number" formControlName="rent_tax_tramo4_rate"></td>
            </tr>
            <tr>
              <td>5+</td>
              <td>Sin límite</td>
              <td><input type="number" formControlName="rent_tax_tramo5_rate"></td>
            </tr>
          </tbody>
        </table>
        
        <div class="actions">
          <button type="submit" [disabled]="!form.valid">Guardar Cambios</button>
          <button type="button" (click)="copyFromPreviousYear()">
            Copiar desde {{ selectedYear - 1 }}
          </button>
        </div>
      </form>
    </div>
  `
})
export class TaxParametersComponent implements OnInit {
  form: FormGroup;
  years: number[] = [2024, 2025, 2026];
  selectedYear = 2025;
  
  constructor(
    private fb: FormBuilder,
    private taxParameterService: TaxParameterService,
    private toastService: ToastService
  ) {}
  
  ngOnInit() {
    this.createForm();
    this.loadYear(this.selectedYear);
  }
  
  loadYear(year: number) {
    this.selectedYear = year;
    this.taxParameterService.getByYear(year).subscribe({
      next: (response) => {
        this.form.patchValue(response.data);
      },
      error: () => {
        this.toastService.error(`No hay parámetros para ${year}`);
      }
    });
  }
  
  calculateFamilyAllowance() {
    const rmv = this.form.get('minimum_wage')?.value;
    if (rmv) {
      const familyAllowance = rmv * 0.10;
      this.form.patchValue({ family_allowance: familyAllowance });
    }
  }
  
  save() {
    if (this.form.valid) {
      this.taxParameterService.update(this.selectedYear, this.form.value).subscribe({
        next: () => {
          this.toastService.success('Parámetros actualizados correctamente');
        },
        error: (err) => {
          this.toastService.error('Error al actualizar parámetros');
        }
      });
    }
  }
  
  copyFromPreviousYear() {
    const previousYear = this.selectedYear - 1;
    this.taxParameterService.copyYear(previousYear, this.selectedYear).subscribe({
      next: () => {
        this.toastService.success(`Parámetros copiados de ${previousYear}`);
        this.loadYear(this.selectedYear);
      },
      error: () => {
        this.toastService.error('Error al copiar parámetros');
      }
    });
  }
}
```

---

## 📝 **EJEMPLO DE USO: ACTUALIZAR PARA 2026**

### **Paso 1: Diciembre 2025 - Copiar parámetros**
```bash
curl -X POST http://localhost:8000/api/v1/hr/tax-parameters/copy-year \
  -H "Content-Type: application/json" \
  -d '{
    "from_year": 2025,
    "to_year": 2026
  }'
```

### **Paso 2: Actualizar valores 2026**
```bash
curl -X PUT http://localhost:8000/api/v1/hr/tax-parameters/2026 \
  -H "Content-Type: application/json" \
  -d '{
    "uit_amount": 5500.00,
    "minimum_wage": 1200.00,
    "family_allowance": 120.00
  }'
```

### **Paso 3: Sistema usa automáticamente año correcto**
```php
// En PayrollCalculationService.php
$year = date('Y'); // 2026
$taxParams = TaxParameter::getActiveForYear($year);
// Usa los parámetros de 2026 automáticamente
```

---

## ✅ **VALORES ACTUALES (14 NOV 2025)**

| Parámetro | Valor |
|-----------|-------|
| UIT 2025 | S/ 5,350 |
| RMV 2025 | S/ 1,130 |
| Asignación Familiar | S/ 113.00 |
| AFP Aporte | 10% |
| AFP Seguro | 0.99% |
| AFP Prima | 1.47% |
| AFP Integra | 1.00% |
| AFP Profuturo | 1.20% |
| AFP Habitat | 1.00% |
| ONP | 13% |
| EsSalud | 9% |

---

**✨ Todo es dinámico y se puede actualizar sin código!** 🎉
