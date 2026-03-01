<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watched_items', function (Blueprint $table) {
            $table->unique(['user_id', 'blizzard_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('watched_items', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'blizzard_item_id']);
        });
    }
};
