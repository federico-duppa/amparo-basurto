<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Un pago imputado a un sobre de gasto puede, además de descontar el
            // saldo, cumplir parte del objetivo: baja la vara por el mismo monto.
            // El objetivo emerge de la historia igual que el saldo.
            $table->boolean('reduces_target')->default(false)->after('rate_source');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('reduces_target');
        });
    }
};
