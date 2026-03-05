<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profession_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('blizzard_recipe_id')->unique();
            $table->string('name');
            $table->foreignId('crafted_item_id_silver')
                ->nullable()
                ->constrained('catalog_items')
                ->nullOnDelete();
            $table->foreignId('crafted_item_id_gold')
                ->nullable()
                ->constrained('catalog_items')
                ->nullOnDelete();
            $table->unsignedSmallInteger('crafted_quantity')->default(1);
            $table->boolean('is_commodity')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('profession_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
