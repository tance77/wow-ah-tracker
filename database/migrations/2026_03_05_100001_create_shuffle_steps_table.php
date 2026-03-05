<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shuffle_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shuffle_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('input_blizzard_item_id');
            $table->unsignedBigInteger('output_blizzard_item_id');
            $table->unsignedInteger('output_qty_min')->default(1);
            $table->unsignedInteger('output_qty_max')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['shuffle_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shuffle_steps');
    }
};
