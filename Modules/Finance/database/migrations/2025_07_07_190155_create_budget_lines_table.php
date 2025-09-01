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
            Schema::create('budget_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('budget_id')->constrained('budgets')->onDelete('cascade');
                $table->foreignId('account_id')->constrained('chart_of_accounts', 'account_id');
                $table->string('description')->nullable();
                $table->decimal('budgeted_amount', 15, 2);
                $table->decimal('executed_amount', 15, 2)->default(0);
                $table->decimal('quarter_1', 15, 2)->default(0);
                $table->decimal('quarter_2', 15, 2)->default(0);
                $table->decimal('quarter_3', 15, 2)->default(0);
                $table->decimal('quarter_4', 15, 2)->default(0);
                $table->timestamps();

                $table->index(['budget_id', 'account_id']);
            });
        }



        /**
         * Reverse the migrations.
         */
        public function down(): void
        {
            Schema::dropIfExists('budget_lines');
        }
    };
