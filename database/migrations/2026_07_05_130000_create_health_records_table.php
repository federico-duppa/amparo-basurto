<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historias clínicas. El titular es una persona (uno mismo, un familiar,
     * un paciente) que no necesita ser usuario de la app; user_id es el dueño
     * de la historia dentro del sistema.
     */
    public function up(): void
    {
        Schema::create('health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('titular');
            $table->date('nacimiento')->nullable();
            $table->string('grupo_sanguineo')->nullable();
            $table->string('obra_social')->nullable();
            $table->text('alergias')->nullable();
            $table->text('condiciones')->nullable();
            $table->text('medicacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_records');
    }
};
