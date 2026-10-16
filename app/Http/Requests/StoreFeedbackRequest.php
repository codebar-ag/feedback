<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Data\FeedbackSubmission;
use App\Enums\ErrorCode;
use App\Enums\Locale;
use App\Http\Responses\ApiError;
use App\Rules\JsonField;
use App\Support\ClientData;
use App\Support\ScreenshotValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreFeedbackRequest extends FormRequest
{
    /** Multipart fields that carry free text and get control characters removed. */
    private const array TEXT_FIELDS = ['message', 'name', 'email', 'page_url', 'page_title', 'referrer', 'submitted_from_url', 'widget_version', 'screenshot_error'];

    /** @var array{mime: string, width: int, height: int, bytes: int}|null */
    private ?array $screenshotInfo = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->rejectBotsSilently();
        $this->rejectOversizedScreenshot();

        $clean = [];

        foreach (self::TEXT_FIELDS as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $clean[$field] = self::stripControlCharacters($value, keepNewlines: $field === 'message');
            }
        }

        $identity = $this->identity();
        $identity = is_array($identity) ? $identity : null;

        $clean['idempotency_key'] = $this->header('Idempotency-Key');
        $clean['email'] = $identity['email'] ?? ($clean['email'] ?? null);
        $clean['name'] = $identity !== null ? ($identity['name'] ?? null) : ($clean['name'] ?? null);

        $this->merge($clean);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hasIdentity = $this->identity() !== null;

        return [
            'idempotency_key' => ['required', 'uuid'],
            'message' => ['required', 'string', 'min:3', 'max:5000'],
            // A name is only accepted from an injected identity (concept 4.11.5).
            'name' => $hasIdentity ? ['nullable', 'string', 'max:120'] : ['prohibited'],
            'email' => ['required', 'string', 'email', 'max:254'],
            'locale' => ['required', Rule::enum(Locale::class)],
            'page_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'page_title' => ['nullable', 'string', 'max:300'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'client' => ['required', new JsonField(JsonField::OBJECT, 4 * 1024)],
            'console_log' => ['nullable', new JsonField(JsonField::LIST, 32 * 1024)],
            'network_log' => ['nullable', new JsonField(JsonField::LIST, 16 * 1024)],
            'annotations' => ['nullable', new JsonField(JsonField::LIST, 16 * 1024, self::isAnnotation(...))],
            'custom_data' => ['nullable', new JsonField(JsonField::FLAT_OBJECT, 8 * 1024)],
            'identity' => ['nullable', new JsonField(JsonField::OBJECT, 1024)],
            'screenshot' => ['nullable', 'file'],
            'submitted_from_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'screenshot_error' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'widget_version' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.+-]+$/'],
            'elapsed_ms' => ['required', 'integer', 'min:0', 'max:4294967295'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $identity = $this->identity();

                // Absent, or malformed JSON which JsonField already reports.
                if (! is_array($identity)) {
                    return;
                }

                foreach (['id' => 191, 'name' => 120, 'email' => 254] as $key => $max) {
                    $value = $identity[$key] ?? null;

                    if ($value !== null && (! is_string($value) || mb_strlen($value) > $max)) {
                        $validator->errors()->add('identity', "The identity.{$key} value is invalid.");
                    }
                }
            },
            function (Validator $validator): void {
                $file = $this->file('screenshot');

                if (! $file instanceof UploadedFile || $validator->errors()->has('screenshot')) {
                    return;
                }

                $result = $this->screenshotValidator()->inspect($file->getRealPath() ?: '');

                if ($result['ok'] === false) {
                    $validator->errors()->add('screenshot', "The screenshot is invalid ({$result['error']}).");

                    return;
                }

                $this->screenshotInfo = [
                    'mime' => $result['mime'],
                    'width' => $result['width'],
                    'height' => $result['height'],
                    'bytes' => $result['bytes'],
                ];
            },
        ];
    }

    public function toSubmission(): FeedbackSubmission
    {
        $identity = $this->identity() ?: null;
        $file = $this->file('screenshot');

        $screenshot = $file instanceof UploadedFile && $this->screenshotInfo !== null
            ? [...$this->screenshotInfo, 'path' => (string) $file->getRealPath()]
            : null;

        $client = self::decode($this->string('client')->value()) ?? [];

        return new FeedbackSubmission(
            idempotencyKey: strtolower($this->string('idempotency_key')->value()),
            message: $this->string('message')->trim()->value(),
            email: $this->string('email')->trim()->value(),
            name: self::nullableString($this->input('name')),
            identityUserId: self::nullableString($identity['id'] ?? null),
            locale: Locale::from($this->string('locale')->value()),
            pageUrl: $this->string('page_url')->value(),
            pageTitle: self::nullableString($this->input('page_title')),
            referrer: self::nullableString($this->input('referrer')),
            client: ClientData::fromArray($client),
            consoleLog: self::decodeList($this->input('console_log')),
            networkLog: self::decodeList($this->input('network_log')),
            annotations: self::decodeList($this->input('annotations')),
            customData: self::decodeObject($this->input('custom_data')),
            screenshot: $screenshot,
            screenshotError: $screenshot === null ? self::nullableString($this->input('screenshot_error')) : null,
            submittedFromUrl: self::nullableString($this->input('submitted_from_url')),
            widgetVersion: $this->string('widget_version')->value(),
            elapsedMs: $this->integer('elapsed_ms'),
        );
    }

    /**
     * Decoded identity: null when absent, false when present but invalid.
     *
     * @return array<string, mixed>|false|null
     */
    private function identity(): array|false|null
    {
        $raw = $this->input('identity');

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = is_string($raw) ? self::decode($raw) : null;

        if ($decoded === null || array_is_list($decoded) && $decoded !== []) {
            return false;
        }

        return $decoded;
    }

    /**
     * Honeypot and minimum fill time are checked before validation. Suspicious
     * requests get a regular 201 without being stored (concept 12.3).
     */
    private function rejectBotsSilently(): void
    {
        $honeypot = $this->input('website');
        $minElapsed = config('feedback.limits.min_elapsed_ms');
        $elapsed = $this->input('elapsed_ms');

        $isBot = (is_string($honeypot) && trim($honeypot) !== '')
            || (is_int($minElapsed) && is_numeric($elapsed) && (int) $elapsed < $minElapsed);

        if (! $isBot) {
            return;
        }

        Log::notice('feedback.rejected', ['reason' => 'bot_suspected', 'project' => $this->attributes->get('feedback_project_id')]);

        throw new HttpResponseException(new JsonResponse(['id' => (string) Str::ulid(), 'status' => 'received'], 201));
    }

    private function rejectOversizedScreenshot(): void
    {
        $file = $this->file('screenshot');

        if (! $file instanceof UploadedFile) {
            return;
        }

        $tooLarge = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || ($file->isValid() && $this->screenshotValidator()->isTooLarge((int) $file->getSize()));

        if ($tooLarge) {
            throw new HttpResponseException(ApiError::response(ErrorCode::PayloadTooLarge, 413));
        }
    }

    private function screenshotValidator(): ScreenshotValidator
    {
        $bytes = config('feedback.max_screenshot_bytes');
        $pixels = config('feedback.max_screenshot_pixels');

        return new ScreenshotValidator(is_int($bytes) ? $bytes : 6 * 1024 * 1024, is_int($pixels) ? $pixels : 25_000_000);
    }

    private static function isAnnotation(mixed $item): bool
    {
        if (! is_array($item) || ! in_array($item['type'] ?? null, ['rect', 'marker'], true)) {
            return false;
        }

        if ($item['type'] === 'rect') {
            foreach (['x', 'y', 'w', 'h'] as $key) {
                if (! is_int($item[$key] ?? null) && ! is_float($item[$key] ?? null)) {
                    return false;
                }
            }

            return true;
        }

        return is_array($item['points'] ?? null);
    }

    private static function stripControlCharacters(string $value, bool $keepNewlines): string
    {
        $pattern = $keepNewlines ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';

        return (string) preg_replace($pattern, '', $value);
    }

    /** @return array<mixed>|null */
    private static function decode(string $value): ?array
    {
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return list<mixed>|null */
    private static function decodeList(mixed $value): ?array
    {
        $decoded = is_string($value) ? self::decode($value) : null;

        return $decoded !== null && array_is_list($decoded) ? $decoded : null;
    }

    /** @return array<string, mixed>|null */
    private static function decodeObject(mixed $value): ?array
    {
        $decoded = is_string($value) ? self::decode($value) : null;

        if ($decoded === null || $decoded === []) {
            return null;
        }

        $object = [];

        foreach ($decoded as $key => $item) {
            $object[(string) $key] = $item;
        }

        return $object;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
