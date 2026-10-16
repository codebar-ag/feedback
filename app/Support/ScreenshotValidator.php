<?php

declare(strict_types=1);

namespace App\Support;

use finfo;

/**
 * Validates an uploaded screenshot by its content (finfo), not by the
 * client supplied MIME type or extension.
 */
final readonly class ScreenshotValidator
{
    public const array ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private int $maxBytes,
        private int $maxPixels,
    ) {}

    public function isTooLarge(int $bytes): bool
    {
        return $bytes > $this->maxBytes;
    }

    /**
     * @return array{ok: true, mime: string, width: int, height: int, bytes: int}|array{ok: false, error: string}
     */
    public function inspect(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return ['ok' => false, 'error' => 'unreadable'];
        }

        $bytes = (int) filesize($path);

        if ($bytes === 0) {
            return ['ok' => false, 'error' => 'empty'];
        }

        if ($this->isTooLarge($bytes)) {
            return ['ok' => false, 'error' => 'too_large'];
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! is_string($mime) || ! in_array($mime, self::ALLOWED_MIME, true)) {
            return ['ok' => false, 'error' => 'invalid_type'];
        }

        $size = @getimagesize($path);

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return ['ok' => false, 'error' => 'invalid_image'];
        }

        if ($this->maxPixels < $size[0] * $size[1]) {
            return ['ok' => false, 'error' => 'dimensions'];
        }

        return ['ok' => true, 'mime' => $mime, 'width' => $size[0], 'height' => $size[1], 'bytes' => $bytes];
    }
}
