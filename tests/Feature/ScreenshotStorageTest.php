<?php

declare(strict_types=1);

use App\Models\FeedbackEntry;
use App\Models\FeedbackProject;
use App\Models\FeedbackScreenshot;
use Illuminate\Http\UploadedFile;

// Runs against SQLite, MySQL and PostgreSQL in CI (blob / LONGBLOB / bytea).
it('round-trips binary screenshot data byte for byte', function (int $bytes): void {
    $entry = FeedbackEntry::factory()->create();
    $data = random_bytes($bytes)."\x00\xFF\x00";

    FeedbackScreenshot::query()->create([
        'entry_id' => $entry->id,
        'mime' => 'image/jpeg',
        'width' => 1,
        'height' => 1,
        'bytes' => strlen($data),
        'sha256' => hash('sha256', $data),
        'data' => $data,
    ]);

    $stored = FeedbackScreenshot::query()->findOrFail($entry->id);

    expect($stored->data)->toBe($data)
        ->and(hash('sha256', (string) $stored->data))->toBe($stored->sha256)
        ->and($stored->entry->is($entry))->toBeTrue()
        ->and($stored->extension())->toBe('jpg');
})->with([
    'small' => [100],
    'over 64 KB (MySQL BLOB limit)' => [200 * 1024],
    'large' => [3 * 1024 * 1024],
]);

it('stores png and webp uploads with their extension', function (string $type, string $ext): void {
    $project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN]]);
    $image = imagecreatetruecolor(30, 20);
    $path = tempnam(sys_get_temp_dir(), 'shot');
    $type === 'png' ? imagepng($image, $path) : imagewebp($image, $path);

    postFeedback($project, ['screenshot' => new UploadedFile((string) $path, "s.{$ext}", null, null, true)])->assertCreated();

    expect(FeedbackScreenshot::query()->sole()->extension())->toBe($ext);
})->with([['png', 'png'], ['webp', 'webp']]);

it('deletes the screenshot with its entry', function (): void {
    $project = FeedbackProject::factory()->create(['allowed_origins' => [TEST_ORIGIN]]);
    postFeedback($project, ['screenshot' => fakeJpeg()])->assertCreated();

    FeedbackEntry::query()->sole()->delete();

    expect(FeedbackScreenshot::query()->count())->toBe(0);
});
