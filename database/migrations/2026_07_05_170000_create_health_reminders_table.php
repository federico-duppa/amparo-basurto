<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vencimientos de una historia clínica: próximo control, receta que
     * caduca, estudio anual… Mismo patrón que la documentación de Auto.
     * user_id es quién lo anotó.
     */
    public function up(): void
    {
        Schema::create('health_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('expires_on');
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_reminders');
    }
};
