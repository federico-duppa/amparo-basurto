<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivote entre tareas y etiquetas. Ambas viven del mismo usuario, así que
     * el scoping lo sigue garantizando la relación de la tarea.
     */
    public function up(): void
    {
        Schema::create('tag_todo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('todo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->unique(['todo_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tag_todo');
    }
};
