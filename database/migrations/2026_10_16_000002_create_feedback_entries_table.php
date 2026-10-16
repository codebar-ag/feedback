<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('project_id')->constrained('feedback_projects')->cascadeOnDelete();
            $table->uuid('client_request_id');
            $table->string('source', 20);
            $table->string('name', 120)->nullable();
            $table->string('email', 254);
            $table->text('message');
            $table->string('locale', 5);
            $table->string('identity_user_id', 191)->nullable();
            $table->text('page_url');
            $table->string('page_title', 300)->nullable();
            $table->text('referrer')->nullable();
            $table->string('origin', 255);
            $table->string('ip', 45)->nullable();
            $table->string('geo_country', 2)->nullable();
            $table->string('geo_city', 120)->nullable();
            $table->string('geo_postcode', 20)->nullable();
            $table->string('os_name', 64)->nullable();
            $table->string('os_version', 64)->nullable();
            $table->string('browser_name', 64)->nullable();
            $table->string('browser_version', 64)->nullable();
            $table->string('device_type', 32)->nullable();
            $table->string('version_precision', 12);
            $table->text('user_agent')->nullable();
            $table->unsignedInteger('viewport_w')->nullable();
            $table->unsignedInteger('viewport_h')->nullable();
            $table->unsignedInteger('screen_w')->nullable();
            $table->unsignedInteger('screen_h')->nullable();
            $table->unsignedInteger('page_w')->nullable();
            $table->unsignedInteger('page_h')->nullable();
            $table->unsignedInteger('scroll_x')->nullable();
            $table->unsignedInteger('scroll_y')->nullable();
            $table->decimal('dpr', 4, 2)->nullable();
            $table->unsignedTinyInteger('color_depth')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('browser_language', 35)->nullable();
            $table->json('console_log')->nullable();
            $table->json('network_log')->nullable();
            $table->json('annotations')->nullable();
            $table->json('custom_data')->nullable();
            $table->string('screenshot_error', 64)->nullable();
            $table->text('submitted_from_url')->nullable();
            $table->string('widget_version', 20);
            $table->unsignedInteger('elapsed_ms')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('internal_note')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'client_request_id']);
            $table->index(['project_id', 'created_at']);
            $table->index(['project_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_entries');
    }
};
