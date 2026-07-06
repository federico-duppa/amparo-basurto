<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices que quedaron afuera de la tanda de 2026_07_05_140000:
 *
 * - La vista "Lista" de Tareas (la default) filtra por user_id, completed_at
 *   IS NULL y status; el índice existente (user_id, completed_at) no cubría
 *   el status.
 * - Los vencimientos de Salud (recordatorios y vacunas) sólo tenían los
 *   índices de FK; el par de Auto (vehicle_documents) ya trae
 *   (vehicle_id, expires_on). Mismo criterio acá.
 *
 * Compatible con SQLite (local) y Postgres (Cloud).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'completed_at']);
        });

        Schema::table('health_reminders', function (Blueprint $table) {
            $table->index(['health_record_id', 'expires_on']);
        });

        Schema::table('health_vaccines', function (Blueprint $table) {
            $table->index(['health_record_id', 'next_due_on']);
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status', 'completed_at']);
        });

        Schema::table('health_reminders', function (Blueprint $table) {
            $table->dropIndex(['health_record_id', 'expires_on']);
        });

        Schema::table('health_vaccines', function (Blueprint $table) {
            $table->dropIndex(['health_record_id', 'next_due_on']);
        });
    }
};
