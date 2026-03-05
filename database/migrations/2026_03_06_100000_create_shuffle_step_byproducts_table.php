<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shuffle_step_byproducts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shuffle_step_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('blizzard_item_id');
            $table->string('item_name');
            $table->decimal('chance_percent', 5, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->index('shuffle_step_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shuffle_step_byproducts');
    }
};
