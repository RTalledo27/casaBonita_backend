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
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('fiscal_year');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_amount', 15, 2);
            $table->enum('status', ['draft', 'approved', 'executed', 'closed'])->default('draft');
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->foreignId('approved_by')->nullable()->constrained('users', 'user_id');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['fiscal_year', 'status']);
            $table->index('created_by');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
