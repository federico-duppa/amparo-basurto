<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un vehículo puede ser un auto o una moto. El tipo cambia la voz, el ícono
     * y los mantenimientos que se precargan al darlo de alta; el resto del
     * seguimiento (km, cargas, documentación) es igual para los dos.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('tipo')->default('auto')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
