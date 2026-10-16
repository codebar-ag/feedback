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
        Schema::create('feedback_screenshots', function (Blueprint $table): void {
            $table->foreignUlid('entry_id')->primary()->constrained('feedback_entries')->cascadeOnDelete();
            $table->string('mime', 20);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('bytes');
            $table->char('sha256', 64);
            // binary() is BLOB (64 KB) on MySQL, bytea on PostgreSQL, blob on SQLite.
            $table->binary('data');
        });

        // MySQL/MariaDB: BLOB is limited to 64 KB, screenshots need LONGBLOB.
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE feedback_screenshots MODIFY data LONGBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_screenshots');
    }
};
