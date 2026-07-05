<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            // Al eliminar un proyecto sus tareas quedan sueltas, no se pierden.
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->boolean('urgent')->default(false);
            $table->boolean('important')->default(false);
            $table->string('repeat_interval', 20)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('todos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn(['due_date', 'urgent', 'important', 'repeat_interval']);
        });
    }
};
