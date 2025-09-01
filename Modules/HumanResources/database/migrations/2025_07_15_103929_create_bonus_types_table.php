<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bonus_types', function (Blueprint $table) {
            $table->id('bonus_type_id');
            $table->string('type_code', 50)->unique();
            $table->string('type_name', 100);
            $table->text('description')->nullable();
            $table->enum('calculation_method', [
                'percentage_of_goal',
                'fixed_amount',
                'sales_count',
                'collection_amount',
                'attendance_rate',
                'custom'
            ]);
            $table->boolean('is_automatic')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->json('applicable_employee_types')->nullable();
            $table->enum('frequency', [
                'monthly',
                'quarterly',
                'biweekly',
                'annual',
                'one_time'
            ]);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type_code', 'is_active']);
            $table->index(['is_automatic', 'is_active']);
        });
    }

   


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bonus_types');
    }
};
