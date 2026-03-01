<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watched_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('blizzard_item_id');
            $table->string('name');
            $table->unsignedInteger('buy_threshold');   // integer percentage (e.g., 15 = 15%)
            $table->unsignedInteger('sell_threshold');  // integer percentage (e.g., 15 = 15%)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watched_items');
    }
};
