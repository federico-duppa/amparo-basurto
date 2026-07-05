<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Carnet de vacunas de una historia clínica: cada aplicación con su
     * vacuna, dosis y fecha, y la próxima dosis si se conoce. user_id es
     * quién la anotó.
     */
    public function up(): void
    {
        Schema::create('health_vaccines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('dose')->nullable();
            $table->date('applied_on');
            $table->date('next_due_on')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_vaccines');
    }
};
