<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cosas por comprar dentro de una lista. Cuando ya se tienen, se sacan de
     * la lista (se borra la fila). user_id guarda quién la anotó (para listas
     * compartidas).
     */
    public function up(): void
    {
        Schema::create('shopping_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index('shopping_list_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_items');
    }
};
