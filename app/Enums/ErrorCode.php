<?php

declare(strict_types=1);

namespace App\Enums;

/** Machine readable error codes of the public API. */
enum ErrorCode: string
{
    case OriginNotAllowed = 'origin_not_allowed';
    case ProjectNotFound = 'project_not_found';
    case PayloadTooLarge = 'payload_too_large';
    case ValidationFailed = 'validation_failed';
    case RateLimited = 'rate_limited';
    case NotFound = 'not_found';
    case MethodNotAllowed = 'method_not_allowed';
    case ServerError = 'server_error';
}
