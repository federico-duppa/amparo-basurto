<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('health_record_id')->constrained()->cascadeOnDelete();
            // Un adjunto puede colgar de una entrada del timeline o quedar
            // suelto en la historia (health_entry_id null).
            $table->foreignId('health_entry_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index(['health_record_id', 'health_entry_id']);
            $table->index('health_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_attachments');
    }
};
