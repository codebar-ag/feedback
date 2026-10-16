<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\ErrorCode;
use Illuminate\Http\JsonResponse;

/** Error body of the public API: {"error": {"code": "...", ...}}. Never contains internals. */
final readonly class ApiError
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, string>  $headers
     */
    public static function response(ErrorCode $code, int $status, array $extra = [], array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code->value, ...$extra]], $status, $headers);
    }
}
