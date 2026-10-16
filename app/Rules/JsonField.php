<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a JSON encoded multipart field: byte size, shape (object, list
 * or flat object) and optionally every list item.
 */
final readonly class JsonField implements ValidationRule
{
    public const string OBJECT = 'object';

    public const string LIST = 'list';

    public const string FLAT_OBJECT = 'flat_object';

    /** @param (Closure(mixed): bool)|null $item */
    public function __construct(
        private string $shape,
        private int $maxBytes,
        private ?Closure $item = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute field must be a JSON string.');

            return;
        }

        if (strlen($value) > $this->maxBytes) {
            $fail("The :attribute field must not be larger than {$this->maxBytes} bytes.");

            return;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            $fail('The :attribute field must be valid JSON.');

            return;
        }

        $isList = array_is_list($decoded);

        $shapeOk = match ($this->shape) {
            self::LIST => $isList,
            self::OBJECT => ! $isList || $decoded === [],
            default => (! $isList || $decoded === []) && self::isFlat($decoded),
        };

        if (! $shapeOk) {
            $fail("The :attribute field must be a JSON {$this->shape}.");

            return;
        }

        if ($this->item !== null) {
            foreach ($decoded as $entry) {
                if (! ($this->item)($entry)) {
                    $fail('The :attribute field contains an invalid entry.');

                    return;
                }
            }
        }
    }

    /** @param array<mixed> $value */
    private static function isFlat(array $value): bool
    {
        foreach ($value as $item) {
            if (! is_scalar($item) && $item !== null) {
                return false;
            }
        }

        return true;
    }
}
