<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tocar una cosa de la lista ya no la borra: la tacha como comprada y
     * queda a la vista hasta que el usuario limpie la lista con el tachito.
     * Null = todavía falta comprarla.
     */
    public function up(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->timestamp('purchased_at')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_items', function (Blueprint $table) {
            $table->dropColumn('purchased_at');
        });
    }
};
