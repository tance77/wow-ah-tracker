<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ingestion_metadata', function (Blueprint $table) {
            $table->string('realm_last_modified_at')->nullable()->after('consecutive_failures');
            $table->string('realm_response_hash')->nullable()->after('realm_last_modified_at');
            $table->timestamp('realm_last_fetched_at')->nullable()->after('realm_response_hash');
            $table->unsignedInteger('realm_consecutive_failures')->default(0)->after('realm_last_fetched_at');
        });
    }

    public function down(): void
    {
        Schema::table('ingestion_metadata', function (Blueprint $table) {
            $table->dropColumn([
                'realm_last_modified_at',
                'realm_response_hash',
                'realm_last_fetched_at',
                'realm_consecutive_failures',
            ]);
        });
    }
};
