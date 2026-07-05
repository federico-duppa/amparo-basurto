<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices compuestos para las consultas que filtran por dueño/relación y
 * ordenan o acotan por fecha. Las FK ya traen índice single-column; lo que
 * faltaba era cubrir el segundo criterio (fecha/estado) que usan los listados
 * y agregados de cada módulo, para no depender de scans a medida que crece la
 * historia. Compatible con SQLite (local) y Postgres (Cloud).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->index(['user_id', 'completed_at']);
            $table->index(['user_id', 'due_date']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->index(['user_id', 'spent_on']);
        });

        Schema::table('envelope_movements', function (Blueprint $table) {
            $table->index(['envelope_id', 'type']);
            $table->index('transfer_group');
        });

        Schema::table('health_entries', function (Blueprint $table) {
            $table->index(['health_record_id', 'occurred_on']);
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->index(['maintenance_item_id', 'performed_on']);
        });

        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->index(['vehicle_id', 'filled_on']);
        });

        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->index(['vehicle_id', 'expires_on']);
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'completed_at']);
            $table->dropIndex(['user_id', 'due_date']);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'spent_on']);
        });

        Schema::table('envelope_movements', function (Blueprint $table) {
            $table->dropIndex(['envelope_id', 'type']);
            $table->dropIndex(['transfer_group']);
        });

        Schema::table('health_entries', function (Blueprint $table) {
            $table->dropIndex(['health_record_id', 'occurred_on']);
        });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropIndex(['maintenance_item_id', 'performed_on']);
        });

        Schema::table('fuel_logs', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'filled_on']);
        });

        Schema::table('vehicle_documents', function (Blueprint $table) {
            $table->dropIndex(['vehicle_id', 'expires_on']);
        });
    }
};
