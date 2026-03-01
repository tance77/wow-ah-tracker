<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watched_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('min_price');     // copper, NOT gold/float
            $table->unsignedBigInteger('avg_price');     // copper, NOT gold/float
            $table->unsignedBigInteger('median_price');  // copper, NOT gold/float
            $table->unsignedBigInteger('total_volume');  // unsigned big integer
            $table->timestamp('polled_at');              // datetime, NOT Unix integer
            $table->timestamps();

            // CRITICAL: Composite index — cannot be efficiently added after data accumulates
            $table->index(['watched_item_id', 'polled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_snapshots');
    }
};
