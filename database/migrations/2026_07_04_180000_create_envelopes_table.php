<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('envelopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind'); // ahorro | gasto (gasto previsto)
            $table->string('currency', 3)->default('ARS');
            $table->boolean('indexed')->default(false); // objetivo indexado por IPC (solo ahorro en ARS)
            $table->decimal('target_amount', 14, 2)->nullable();
            $table->date('target_month')->nullable(); // mes base en que está expresado el objetivo indexado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envelopes');
    }
};
