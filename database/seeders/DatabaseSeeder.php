<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Runs on every deploy (php artisan migrate --seed). There is no required
 * data: projects are created with php artisan feedback:project:create.
 * Anything added here must be idempotent.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
