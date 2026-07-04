<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Si se elimina el sobre, el gasto queda suelto: es historia real, no se borra.
            $table->foreignId('envelope_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('category');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->date('spent_on');
            $table->decimal('rate_ars', 14, 4)->nullable(); // snapshot: ARS por unidad de la moneda, del día del gasto
            $table->string('rate_source')->nullable(); // blue | oficial | mep
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
