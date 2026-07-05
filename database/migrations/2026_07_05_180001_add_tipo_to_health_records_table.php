<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El titular de una historia puede ser una persona, una mascota o un
     * documento (una ficha de paciente sin persona detrás). El tipo adapta la
     * ficha: la mascota trae especie y raza y usa la veterinaria en lugar de la
     * obra social; el documento se queda con una ficha neutra. Las secciones
     * (timeline, vencimientos, vacunas, mediciones, contactos) son iguales para
     * los tres.
     */
    public function up(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $table->string('tipo')->default('persona')->after('user_id');
            $table->string('especie')->nullable()->after('nacimiento');
            $table->string('raza')->nullable()->after('especie');
        });
    }

    public function down(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'especie', 'raza']);
        });
    }
};
