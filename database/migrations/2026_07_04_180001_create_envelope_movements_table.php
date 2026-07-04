<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envelope_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('envelope_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // aporte | retiro | transfer_in | transfer_out
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->date('moved_on');
            $table->string('note')->nullable();
            $table->uuid('transfer_group')->nullable(); // vincula las dos patas de una transferencia
            $table->decimal('exchange_rate', 14, 4)->nullable(); // cotización usada si la transferencia convirtió moneda
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envelope_movements');
    }
};
