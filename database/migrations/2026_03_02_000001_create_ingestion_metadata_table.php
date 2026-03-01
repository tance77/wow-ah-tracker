<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingestion_metadata', function (Blueprint $table): void {
            $table->id();
            $table->string('last_modified_at')->nullable();
            $table->string('response_hash')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_metadata');
    }
};
