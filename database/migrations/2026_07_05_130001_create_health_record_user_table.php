<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usuarios con los que se comparte una historia clínica. El dueño no vive
     * acá (va en health_records.user_id); esta tabla es sólo para el acceso
     * compartido.
     */
    public function up(): void
    {
        Schema::create('health_record_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['health_record_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_record_user');
    }
};
