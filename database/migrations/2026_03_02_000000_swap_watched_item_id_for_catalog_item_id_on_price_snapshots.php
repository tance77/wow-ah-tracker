<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add nullable catalog_item_id FK
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        // 2. Backfill catalog_item_id from watched_items → catalog_items via blizzard_item_id
        DB::statement('
            UPDATE price_snapshots
            SET catalog_item_id = (
                SELECT ci.id
                FROM watched_items wi
                JOIN catalog_items ci ON ci.blizzard_item_id = wi.blizzard_item_id
                WHERE wi.id = price_snapshots.watched_item_id
                LIMIT 1
            )
        ');

        // 3. Delete orphaned rows (no matching catalog item)
        DB::table('price_snapshots')->whereNull('catalog_item_id')->delete();

        // 4. Deduplicate: when multiple users watched the same item, keep only one snapshot per (catalog_item_id, polled_at)
        $duplicateIds = DB::table('price_snapshots as ps')
            ->join(
                DB::raw('(SELECT MIN(id) as keep_id, catalog_item_id, polled_at FROM price_snapshots GROUP BY catalog_item_id, polled_at) as keepers'),
                function ($join) {
                    $join->on('ps.catalog_item_id', '=', 'keepers.catalog_item_id')
                        ->on('ps.polled_at', '=', 'keepers.polled_at')
                        ->whereColumn('ps.id', '!=', 'keepers.keep_id');
                }
            )
            ->pluck('ps.id');

        if ($duplicateIds->isNotEmpty()) {
            DB::table('price_snapshots')->whereIn('id', $duplicateIds)->delete();
        }

        // 5. Make column non-nullable
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable(false)->change();
        });

        // 6. Drop old foreign key, index, and column
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->dropForeign(['watched_item_id']);
            $table->dropIndex(['watched_item_id', 'polled_at']);
            $table->dropColumn('watched_item_id');
        });

        // 7. Add new composite index
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->index(['catalog_item_id', 'polled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->dropIndex(['catalog_item_id', 'polled_at']);
            $table->foreignId('watched_item_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // Backfill is lossy — cannot perfectly restore watched_item_id
        // Just drop the new column
        Schema::table('price_snapshots', function (Blueprint $table) {
            $table->dropForeign(['catalog_item_id']);
            $table->dropColumn('catalog_item_id');
            $table->index(['watched_item_id', 'polled_at']);
        });
    }
};
