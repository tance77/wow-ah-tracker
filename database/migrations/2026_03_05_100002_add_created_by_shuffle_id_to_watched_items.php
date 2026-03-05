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
            $table->foreignId('created_by_shuffle_id')
                ->nullable()
                ->after('profession')
                ->constrained('shuffles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('watched_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_shuffle_id');
        });
    }
};
