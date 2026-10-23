<?php

declare(strict_types=1);

namespace App\Checks;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Writes, reads and deletes a small file on the default disk. Fails when
 * the bucket's credentials or endpoint are wrong. Warns while the default
 * disk is a local one: the container's disk is ephemeral on Laravel Cloud,
 * so files written there are lost on the next deploy or wake-up.
 */
final class StorageRoundTripCheck extends Check
{
    public function run(): Result
    {
        $disk = config('filesystems.default');
        $disk = is_string($disk) ? $disk : '';
        $driver = config("filesystems.disks.{$disk}.driver");
        $driver = is_string($driver) ? $driver : '';
        $result = Result::make()->shortSummary("{$disk} ({$driver})");

        if ($driver === 'local') {
            return $result->warning("The default disk `{$disk}` is local; files are lost on the next deploy. Attach a bucket and set FILESYSTEM_DISK.");
        }

        $path = 'health/'.Str::uuid()->toString().'.txt';
        $content = 'ok '.now()->toIso8601String();

        try {
            Storage::disk($disk)->put($path, $content);
            $read = Storage::disk($disk)->get($path);
            Storage::disk($disk)->delete($path);
        } catch (Throwable $exception) {
            return $result->failed("Storage round trip on `{$disk}` failed: {$exception->getMessage()}");
        }

        return $read === $content
            ? $result->ok()
            : $result->failed("Storage round trip on `{$disk}` read back different content.");
    }
}
