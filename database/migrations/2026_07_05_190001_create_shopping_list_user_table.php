<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usuarios con los que se comparte una lista. El dueño no vive acá
     * (va en shopping_lists.user_id); esta tabla es sólo para el acceso
     * compartido.
     */
    public function up(): void
    {
        Schema::create('shopping_list_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shopping_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['shopping_list_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shopping_list_user');
    }
};
