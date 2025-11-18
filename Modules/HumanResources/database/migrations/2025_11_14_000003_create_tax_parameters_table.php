<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Crear tabla de parámetros tributarios para cálculo de nóminas
     */
    public function up(): void
    {
        Schema::create('tax_parameters', function (Blueprint $table) {
            $table->id('parameter_id');
            $table->integer('year')->unique()->comment('Año fiscal');
            
            // Valores base
            $table->decimal('uit_amount', 10, 2)
                ->default(5150.00)
                ->comment('Unidad Impositiva Tributaria');
            
            $table->decimal('family_allowance', 10, 2)
                ->default(102.50)
                ->comment('Asignación Familiar');
            
            $table->decimal('minimum_wage', 10, 2)
                ->default(1025.00)
                ->comment('Remuneración Mínima Vital');
            
            // AFP - Tasas actualizables
            $table->decimal('afp_contribution_rate', 5, 2)
                ->default(10.00)
                ->comment('Aporte obligatorio AFP (%)');
            
            $table->decimal('afp_insurance_rate', 5, 2)
                ->default(0.99)
                ->comment('Seguro AFP (%)');
            
            $table->decimal('afp_prima_commission', 5, 2)
                ->default(1.47)
                ->comment('Comisión Prima AFP (%)');
            
            $table->decimal('afp_integra_commission', 5, 2)
                ->default(1.00)
                ->comment('Comisión Integra AFP (%)');
            
            $table->decimal('afp_profuturo_commission', 5, 2)
                ->default(1.20)
                ->comment('Comisión Profuturo AFP (%)');
            
            $table->decimal('afp_habitat_commission', 5, 2)
                ->default(1.00)
                ->comment('Comisión Habitat AFP (%)');
            
            // ONP
            $table->decimal('onp_rate', 5, 2)
                ->default(13.00)
                ->comment('Tasa ONP (%)');
            
            // EsSalud (informativo - pagado por empleador)
            $table->decimal('essalud_rate', 5, 2)
                ->default(9.00)
                ->comment('Aporte EsSalud del empleador (%)');
            
            // Impuesto a la Renta - Tramos (en UIT)
            $table->decimal('rent_tax_deduction_uit', 5, 2)
                ->default(7.00)
                ->comment('Deducción anual en UIT');
            
            $table->decimal('rent_tax_tramo1_uit', 5, 2)
                ->default(5.00)
                ->comment('Hasta 5 UIT');
            
            $table->decimal('rent_tax_tramo1_rate', 5, 2)
                ->default(8.00)
                ->comment('Tasa tramo 1 (%)');
            
            $table->decimal('rent_tax_tramo2_uit', 5, 2)
                ->default(20.00)
                ->comment('De 5 a 20 UIT');
            
            $table->decimal('rent_tax_tramo2_rate', 5, 2)
                ->default(14.00)
                ->comment('Tasa tramo 2 (%)');
            
            $table->decimal('rent_tax_tramo3_uit', 5, 2)
                ->default(35.00)
                ->comment('De 20 a 35 UIT');
            
            $table->decimal('rent_tax_tramo3_rate', 5, 2)
                ->default(17.00)
                ->comment('Tasa tramo 3 (%)');
            
            $table->decimal('rent_tax_tramo4_uit', 5, 2)
                ->default(45.00)
                ->comment('De 35 a 45 UIT');
            
            $table->decimal('rent_tax_tramo4_rate', 5, 2)
                ->default(20.00)
                ->comment('Tasa tramo 4 (%)');
            
            $table->decimal('rent_tax_tramo5_rate', 5, 2)
                ->default(30.00)
                ->comment('Más de 45 UIT (%)');
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        
        // Insertar parámetros para 2025 (valores oficiales gob.pe)
        DB::table('tax_parameters')->insert([
            'year' => 2025,
            'uit_amount' => 5350.00, // ✅ Decreto Supremo actualizado 2025
            'family_allowance' => 107.00, // 10% de RMV (S/ 1,070)
            'minimum_wage' => 1070.00, // ✅ RMV 2025
            'afp_contribution_rate' => 10.00,
            'afp_insurance_rate' => 0.99,
            'afp_prima_commission' => 1.47,
            'afp_integra_commission' => 1.00,
            'afp_profuturo_commission' => 1.20,
            'afp_habitat_commission' => 1.00,
            'onp_rate' => 13.00,
            'essalud_rate' => 9.00,
            'rent_tax_deduction_uit' => 7.00,
            'rent_tax_tramo1_uit' => 5.00,
            'rent_tax_tramo1_rate' => 8.00,
            'rent_tax_tramo2_uit' => 20.00,
            'rent_tax_tramo2_rate' => 14.00,
            'rent_tax_tramo3_uit' => 35.00,
            'rent_tax_tramo3_rate' => 17.00,
            'rent_tax_tramo4_uit' => 45.00,
            'rent_tax_tramo4_rate' => 20.00,
            'rent_tax_tramo5_rate' => 30.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_parameters');
    }
};
