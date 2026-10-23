<?php

declare(strict_types=1);

use App\Checks\StorageRoundTripCheck;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Spatie\Health\Enums\Status;

it('passes when the default disk can write, read and delete', function () {
    config(['filesystems.default' => 's3', 'filesystems.disks.s3.driver' => 's3']);
    Storage::fake('s3');

    expect((new StorageRoundTripCheck)->run()->status->equals(Status::ok()))->toBeTrue()
        ->and(Storage::disk('s3')->allFiles('health'))->toBe([]);
})->group('unit', 'checks');

it('warns while the default disk is local', function () {
    config(['filesystems.default' => 'local', 'filesystems.disks.local.driver' => 'local']);

    expect((new StorageRoundTripCheck)->run()->status->equals(Status::warning()))->toBeTrue();
})->group('unit', 'checks');

it('fails when the disk throws', function () {
    config(['filesystems.default' => 's3', 'filesystems.disks.s3.driver' => 's3']);
    Storage::shouldReceive('disk')->with('s3')->andThrow(new RuntimeException('bucket gone'));

    $result = (new StorageRoundTripCheck)->run();

    expect($result->status->equals(Status::failed()))->toBeTrue()
        ->and($result->getNotificationMessage())->toContain('bucket gone');
})->group('unit', 'checks');

it('fails when the file reads back different content', function () {
    config(['filesystems.default' => 's3', 'filesystems.disks.s3.driver' => 's3']);
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('put')->andReturnTrue();
    $disk->shouldReceive('get')->andReturn('something else');
    $disk->shouldReceive('delete')->andReturnTrue();
    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);

    expect((new StorageRoundTripCheck)->run()->getNotificationMessage())->toContain('different content');
})->group('unit', 'checks');
