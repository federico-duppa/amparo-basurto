<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El titular de una historia puede ser una persona, una mascota o un
     * documento (valores de App\Enums\HealthSubjectType). Las historias
     * existentes quedan como persona.
     */
    public function up(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $table->string('titular_tipo')->default('persona');
        });
    }

    public function down(): void
    {
        Schema::table('health_records', function (Blueprint $table) {
            $table->dropColumn('titular_tipo');
        });
    }
};
