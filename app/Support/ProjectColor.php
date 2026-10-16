<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ColorPreset;
use InvalidArgumentException;

/**
 * Project color and the tokens derived from it (concept 4.6). The widget
 * implements the same algorithm in widget/src/theme/derive.ts.
 */
final readonly class ProjectColor
{
    public const string WHITE = '#FFFFFF';

    public const string BLACK = '#000000';

    public const string INK = '#101F3C';

    public const float MIN_CONTRAST = 4.5;

    private const float LIGHT_LUMINANCE = 0.85;

    private function __construct(
        public string $primary,
        public string $onPrimary,
        public float $contrast,
        public string $strong,
        public string $onStrong,
        public float $strongContrast,
        public bool $divider,
    ) {}

    /** Accepts "black", "white" or a hex value (#RRGGBB, #RGB, with or without "#"). */
    public static function fromInput(string $input): self
    {
        $hex = self::parse($input);

        if ($hex === null) {
            throw new InvalidArgumentException("Invalid color [{$input}]. Use black, white or a hex value like #0A7C66.");
        }

        return self::derive($hex);
    }

    public static function parse(string $input): ?string
    {
        $value = strtolower(trim($input));
        $preset = ColorPreset::tryFrom($value);

        if ($preset !== null) {
            return $preset->hex();
        }

        $value = ltrim($value, '#');

        if (preg_match('/^[0-9a-f]{3}$/', $value) === 1) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        if (preg_match('/^[0-9a-f]{6}$/', $value) !== 1) {
            return null;
        }

        return '#'.strtoupper($value);
    }

    public static function derive(string $hex): self
    {
        $primary = strtoupper($hex);
        $onPrimary = self::textOn($primary);

        $strong = $primary;
        $onStrong = $onPrimary;
        $step = 0;

        while (self::contrast($onStrong, $strong) < self::MIN_CONTRAST && $step < 100) {
            $step += 2;
            $strong = self::mix($primary, $onStrong === self::WHITE ? self::BLACK : self::WHITE, $step);
            $onStrong = self::textOn($strong);
        }

        $divider = self::luminance($primary) > self::LIGHT_LUMINANCE;

        if ($divider) {
            $strong = self::INK;
            $onStrong = self::WHITE;
        }

        return new self(
            primary: $primary,
            onPrimary: $onPrimary,
            contrast: round(self::contrast($onPrimary, $primary), 2),
            strong: $strong,
            onStrong: $onStrong,
            strongContrast: round(self::contrast($onStrong, $strong), 2),
            divider: $divider,
        );
    }

    /** White or ink, whichever has the higher contrast on the given color. */
    public static function textOn(string $hex): string
    {
        return self::contrast(self::WHITE, $hex) >= self::contrast(self::INK, $hex) ? self::WHITE : self::INK;
    }

    /** WCAG 2.x contrast ratio. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public function formattedContrast(): string
    {
        return number_format($this->contrast, 2, '.', '').':1';
    }

    /** @return array{primary: string, on_primary: string, contrast: float, strong: string, on_strong: string, strong_contrast: float, divider: bool} */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'on_primary' => $this->onPrimary,
            'contrast' => $this->contrast,
            'strong' => $this->strong,
            'on_strong' => $this->onStrong,
            'strong_contrast' => $this->strongContrast,
            'divider' => $this->divider,
        ];
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    private static function mix(string $from, string $to, int $percent): string
    {
        $a = self::rgb($from);
        $b = self::rgb($to);
        $mixed = '';

        foreach ([0, 1, 2] as $i) {
            $value = (int) round($a[$i] + ($b[$i] - $a[$i]) * $percent / 100);
            $mixed .= str_pad(dechex(max(0, min(255, $value))), 2, '0', STR_PAD_LEFT);
        }

        return '#'.strtoupper($mixed);
    }
}
