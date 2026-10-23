<?php

declare(strict_types=1);

use App\Checks\PendingMigrationsCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Health\Enums\Status;

uses(RefreshDatabase::class);

it('passes when every migration has run', function () {
    expect((new PendingMigrationsCheck)->run()->status->equals(Status::ok()))->toBeTrue();
})->group('unit', 'checks');

it('fails when a migration has not run', function () {
    $latest = DB::table('migrations')->orderByDesc('id')->value('migration');
    DB::table('migrations')->where('migration', $latest)->delete();

    $result = (new PendingMigrationsCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->getNotificationMessage())->toContain($latest);
})->group('unit', 'checks');
