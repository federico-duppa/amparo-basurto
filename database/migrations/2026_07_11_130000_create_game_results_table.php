<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Victorias de Juegos, para mejores tiempos y racha del puzzle del día.
     * Una fila por partida ganada; las diarias llevan la fecha del puzzle en
     * played_on (a lo sumo una por juego y día, garantizado en código).
     */
    public function up(): void
    {
        Schema::create('game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game');
            $table->boolean('daily')->default(false);
            $table->date('played_on');
            $table->unsignedInteger('seconds');
            $table->timestamps();

            $table->index(['user_id', 'game', 'daily', 'played_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_results');
    }
};
