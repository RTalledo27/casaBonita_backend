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
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('description');
            $table->enum('category', ['operations', 'investments', 'financing']);
            $table->enum('type', ['income', 'expense']);
            $table->decimal('amount', 15, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('cost_center_id')->nullable()->constrained('cost_centers', 'id');
            $table->foreignId('created_by')->constrained('users', 'user_id');
            $table->timestamps();

            $table->index(['date', 'type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('cost_center_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_flows');
    }
};
