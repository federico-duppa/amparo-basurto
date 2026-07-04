<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inflation_rates', function (Blueprint $table) {
            $table->id();
            $table->date('period')->unique(); // primer día del mes
            $table->decimal('monthly_pct', 8, 4); // variación mensual del IPC, en %
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inflation_rates');
    }
};
