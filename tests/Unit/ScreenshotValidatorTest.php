<?php

declare(strict_types=1);

use App\Support\ScreenshotValidator;

function imageFile(string $type, int $w = 40, int $h = 20): string
{
    $image = imagecreatetruecolor($w, $h);
    $path = tempnam(sys_get_temp_dir(), 'shot');
    match ($type) {
        'jpeg' => imagejpeg($image, $path),
        'png' => imagepng($image, $path),
        'webp' => imagewebp($image, $path),
        'gif' => imagegif($image, $path),
    };

    return (string) $path;
}

it('accepts jpeg, png and webp by content', function (string $type, string $mime): void {
    $result = (new ScreenshotValidator(6 * 1024 * 1024, 25_000_000))->inspect(imageFile($type, 40, 20));

    expect($result)->toMatchArray(['ok' => true, 'mime' => $mime, 'width' => 40, 'height' => 20]);
})->with([
    ['jpeg', 'image/jpeg'],
    ['png', 'image/png'],
    ['webp', 'image/webp'],
]);

it('rejects other content', function (string $content, string $error): void {
    $path = (string) tempnam(sys_get_temp_dir(), 'shot');
    file_put_contents($path, $content);

    expect((new ScreenshotValidator(1024 * 1024, 25_000_000))->inspect($path))->toBe(['ok' => false, 'error' => $error]);
})->with([
    'html as jpg' => ['<html><script>alert(1)</script></html>', 'invalid_type'],
    'php' => ['<?php echo 1;', 'invalid_type'],
    'empty' => ['', 'empty'],
    'truncated jpeg' => ["\xFF\xD8\xFF\xE0\x00\x10JFIF\x00", 'invalid_image'],
]);

it('rejects gif', function (): void {
    expect((new ScreenshotValidator(1024 * 1024, 25_000_000))->inspect(imageFile('gif')))->toBe(['ok' => false, 'error' => 'invalid_type']);
});

it('enforces the byte and pixel limits', function (): void {
    $path = imageFile('png', 100, 100);

    expect((new ScreenshotValidator(10, 25_000_000))->inspect($path))->toBe(['ok' => false, 'error' => 'too_large'])
        ->and((new ScreenshotValidator(1024 * 1024, 9_999))->inspect($path))->toBe(['ok' => false, 'error' => 'dimensions'])
        ->and((new ScreenshotValidator(10, 1))->isTooLarge(11))->toBeTrue()
        ->and((new ScreenshotValidator(10, 1))->isTooLarge(10))->toBeFalse();
});

it('handles missing files', function (): void {
    expect((new ScreenshotValidator(10, 10))->inspect('/does/not/exist'))->toBe(['ok' => false, 'error' => 'unreadable']);
});
