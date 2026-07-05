<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Segundo lote de campos del módulo Tareas: notas, estado (activa / algún
     * día), en espera, posponer (tickler) y orden manual. Los índices
     * (user_id, completed_at) y (user_id, due_date) que aprovecha el orden en
     * SQL de la vista Lista ya viven en add_performance_indexes; acá sólo se
     * suma el del tickler, que filtra las pospuestas en cada render.
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('title');
            // 'activa' = en el flujo del día; 'algun_dia' = guardada sin apuro.
            $table->string('status', 20)->default('activa')->after('completed_at');
            $table->boolean('waiting')->default(false)->after('important');
            $table->string('waiting_for', 120)->nullable()->after('waiting');
            // Tickler de GTD: la tarea no se muestra hasta esta fecha.
            $table->date('deferred_until')->nullable()->after('due_date');
            // Orden manual dentro de un mismo cuadrante de Eisenhower.
            $table->integer('position')->default(0)->after('deferred_until');

            $table->index(['user_id', 'deferred_until']);
        });
    }

    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'deferred_until']);
            $table->dropColumn([
                'notes', 'status', 'waiting', 'waiting_for', 'deferred_until', 'position',
            ]);
        });
    }
};
