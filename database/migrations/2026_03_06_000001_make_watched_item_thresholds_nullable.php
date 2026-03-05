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
            $table->unsignedInteger('buy_threshold')->nullable()->change();
            $table->unsignedInteger('sell_threshold')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('watched_items', function (Blueprint $table) {
            $table->unsignedInteger('buy_threshold')->nullable(false)->default(10)->change();
            $table->unsignedInteger('sell_threshold')->nullable(false)->default(10)->change();
        });
    }
};
