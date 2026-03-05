<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('professions', function (Blueprint $table) {
            $table->string('slug')->unique()->after('name');
        });

        // Backfill existing rows
        $professions = DB::table('professions')->get();
        foreach ($professions as $profession) {
            DB::table('professions')
                ->where('id', $profession->id)
                ->update(['slug' => Str::slug($profession->name)]);
        }
    }

    public function down(): void
    {
        Schema::table('professions', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
