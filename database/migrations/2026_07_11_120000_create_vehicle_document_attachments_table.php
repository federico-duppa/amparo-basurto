<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_document_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('vehicle_document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_document_attachments');
    }
};
