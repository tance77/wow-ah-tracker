<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shuffle_steps', function (Blueprint $table) {
            $table->unsignedInteger('input_qty')->default(1)->after('output_blizzard_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('shuffle_steps', function (Blueprint $table) {
            $table->dropColumn('input_qty');
        });
    }
};
