<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->string('rate_type'); // blue | oficial | mep
            $table->date('quoted_on');
            $table->decimal('buy', 14, 4)->nullable();
            $table->decimal('sell', 14, 4);
            $table->timestamps();
            $table->unique(['rate_type', 'quoted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
