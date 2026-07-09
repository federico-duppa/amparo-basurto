<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ponderación de uso de cada frecuente: sube al anotarlo en una lista y al
     * tacharlo como comprado. Los frecuentes se muestran ordenados por peso,
     * así lo que más se compra queda primero, a un toque.
     */
    public function up(): void
    {
        Schema::table('frequent_items', function (Blueprint $table) {
            $table->unsignedInteger('weight')->default(0)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('frequent_items', function (Blueprint $table) {
            $table->dropColumn('weight');
        });
    }
};
