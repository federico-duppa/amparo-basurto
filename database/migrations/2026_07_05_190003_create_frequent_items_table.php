<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frecuentes: el repertorio personal de cosas que uno suele comprar, para
     * sumarlas a una lista con un toque. Son de cada usuario (no de la lista),
     * así el mismo repertorio sirve para todas sus listas.
     */
    public function up(): void
    {
        Schema::create('frequent_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frequent_items');
    }
};
