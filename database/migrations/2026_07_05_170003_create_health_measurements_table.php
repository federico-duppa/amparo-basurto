<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mediciones de una historia clínica (peso, presión, glucemia…) para
     * seguir su evolución en el tiempo. value2 solo se usa en mediciones
     * de dos valores (la mínima de la presión). user_id es quién la anotó.
     */
    public function up(): void
    {
        Schema::create('health_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('value', 8, 2);
            $table->decimal('value2', 8, 2)->nullable();
            $table->date('measured_on');
            $table->timestamps();

            $table->index(['health_record_id', 'type', 'measured_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_measurements');
    }
};
