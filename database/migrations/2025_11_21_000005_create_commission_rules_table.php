<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('scheme_id');
            $table->integer('min_sales')->default(0);
            $table->integer('max_sales')->nullable();
            $table->string('term_group')->nullable();
            $table->decimal('percentage', 8, 2)->default(0);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->foreign('scheme_id')->references('id')->on('commission_schemes')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('commission_rules');
    }
};
