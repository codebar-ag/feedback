<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Binary column (LONGBLOB / bytea / blob). Values are written as stream
 * resources so PDO binds them as LOB (required for bytea), and streams
 * returned by pdo_pgsql are read back into strings.
 *
 * @implements CastsAttributes<string|null, mixed>
 */
final class Binary implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_resource($value)) {
            $contents = stream_get_contents($value, -1, 0);

            return $contents === false ? null : $contents;
        }

        return is_string($value) ? $value : null;
    }

    /** @return resource|null */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || is_resource($value)) {
            return $value;
        }

        $stream = fopen('php://memory', 'w+b');

        if ($stream === false) {
            return null;
        }

        fwrite($stream, is_string($value) ? $value : '');
        rewind($stream);

        return $stream;
    }
}
