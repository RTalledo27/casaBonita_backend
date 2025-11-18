<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Actualizar estructura de la tabla payrolls para desglose de AFP/ONP
     */
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // 1. Agregar asignación familiar
            $table->decimal('family_allowance', 10, 2)
                ->default(0)
                ->after('base_salary')
                ->comment('Asignación Familiar S/ 102.50');
            
            // 2. Agregar campos del sistema pensionario (después de gross_salary)
            $table->enum('pension_system', ['AFP', 'ONP', 'NINGUNO'])
                ->default('AFP')
                ->after('gross_salary')
                ->comment('Sistema de pensiones');
            
            $table->string('afp_provider', 50)
                ->nullable()
                ->after('pension_system')
                ->comment('Proveedor de AFP');
            
            $table->decimal('afp_contribution', 10, 2)
                ->default(0)
                ->after('afp_provider')
                ->comment('Aporte obligatorio AFP 10%');
            
            $table->decimal('afp_commission', 10, 2)
                ->default(0)
                ->after('afp_contribution')
                ->comment('Comisión AFP 1.00-1.47%');
            
            $table->decimal('afp_insurance', 10, 2)
                ->default(0)
                ->after('afp_commission')
                ->comment('Seguro AFP 0.99%');
            
            $table->decimal('onp_contribution', 10, 2)
                ->default(0)
                ->after('afp_insurance')
                ->comment('Aporte ONP 13%');
            
            $table->decimal('total_pension', 10, 2)
                ->default(0)
                ->after('onp_contribution')
                ->comment('Total sistema pensionario');
            
            // 3. Agregar campo para aportación del empleador (informativo)
            $table->decimal('employer_essalud', 10, 2)
                ->default(0)
                ->after('total_pension')
                ->comment('EsSalud pagado por empleador (9% - informativo)');
        });
        
        // 4. Renombrar income_tax a rent_tax_5th
        Schema::table('payrolls', function (Blueprint $table) {
            $table->renameColumn('income_tax', 'rent_tax_5th');
        });
        
        // 5. Eliminar columnas obsoletas
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn(['social_security', 'health_insurance']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // Restaurar columnas antiguas
            $table->decimal('social_security', 10, 2)->default(0);
            $table->decimal('health_insurance', 10, 2)->default(0);
        });
        
        // Renombrar de vuelta
        Schema::table('payrolls', function (Blueprint $table) {
            $table->renameColumn('rent_tax_5th', 'income_tax');
        });
        
        // Eliminar nuevas columnas
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'family_allowance',
                'pension_system',
                'afp_provider',
                'afp_contribution',
                'afp_commission',
                'afp_insurance',
                'onp_contribution',
                'total_pension',
                'employer_essalud'
            ]);
        });
    }
};
