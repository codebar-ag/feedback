<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_projects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->string('public_key', 48)->unique();
            $table->json('theme')->nullable();
            $table->json('allowed_origins');
            $table->json('allowed_ips');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('daily_limit')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_projects');
    }
};
