<?php

namespace Modules\HumanResources\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'payroll_id' => $this->payroll_id,
            'payroll_period' => $this->payroll_period,
            'pay_period_start' => $this->pay_period_start?->format('Y-m-d'),
            'pay_period_end' => $this->pay_period_end?->format('Y-m-d'),
            'pay_date' => $this->pay_date?->format('Y-m-d'),
            
            // Ingresos
            'base_salary' => $this->base_salary,
            'family_allowance' => $this->family_allowance,
            'commissions_amount' => $this->commissions_amount,
            'bonuses_amount' => $this->bonuses_amount,
            'overtime_amount' => $this->overtime_amount,
            'other_income' => $this->other_income,
            'gross_salary' => $this->gross_salary,
            
            // Sistema Pensionario
            'pension_system' => $this->pension_system,
            'afp_provider' => $this->afp_provider,
            'afp_contribution' => $this->afp_contribution,
            'afp_commission' => $this->afp_commission,
            'afp_insurance' => $this->afp_insurance,
            'onp_contribution' => $this->onp_contribution,
            'total_pension' => $this->total_pension,
            
            // Impuesto a la Renta
            'rent_tax_5th' => $this->rent_tax_5th,
            
            // Seguro de Salud
            'employee_essalud' => $this->employee_essalud,
            
            // Aportaciones del Empleador (informativo)
            'employer_essalud' => $this->employer_essalud,
            
            // Deducciones y Neto
            'other_deductions' => $this->other_deductions,
            'total_deductions' => $this->total_deductions,
            'net_salary' => $this->net_salary,
            
            'currency' => $this->currency,
            'status' => $this->status,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),

            // Relaciones
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'employee_id' => $this->employee->employee_id,
                    'employee_code' => $this->employee->employee_code,
                    'full_name' => $this->employee->full_name,
                    'employee_type' => $this->employee->employee_type
                ];
            }),

            'processor' => $this->whenLoaded('processor', function () {
                return [
                    'employee_id' => $this->processor->employee_id,
                    'full_name' => $this->processor->full_name
                ];
            }),

            'approver' => $this->whenLoaded('approver', function () {
                return [
                    'employee_id' => $this->approver->employee_id,
                    'full_name' => $this->approver->full_name
                ];
            })
        ];
    }
}
