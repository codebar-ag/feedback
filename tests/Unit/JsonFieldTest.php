<?php

declare(strict_types=1);

use App\Rules\JsonField;
use Illuminate\Support\Facades\Validator;

function jsonPasses(JsonField $rule, mixed $value): bool
{
    return Validator::make(['f' => $value], ['f' => [$rule]])->passes();
}

it('validates JSON shapes and sizes', function (string $shape, mixed $value, bool $passes): void {
    expect(jsonPasses(new JsonField($shape, 64), $value))->toBe($passes);
})->with([
    'object' => [JsonField::OBJECT, '{"a":1}', true],
    'empty object' => [JsonField::OBJECT, '{}', true],
    'list as object' => [JsonField::OBJECT, '[1]', false],
    'list' => [JsonField::LIST, '[{"a":1}]', true],
    'object as list' => [JsonField::LIST, '{"a":1}', false],
    'flat object' => [JsonField::FLAT_OBJECT, '{"a":1,"b":"x","c":null}', true],
    'nested object' => [JsonField::FLAT_OBJECT, '{"a":{"b":1}}', false],
    'invalid json' => [JsonField::OBJECT, '{a:1}', false],
    'scalar json' => [JsonField::OBJECT, '1', false],
    'not a string' => [JsonField::OBJECT, ['a' => 1], false],
    'too large' => [JsonField::LIST, '['.str_repeat('1,', 40).'1]', false],
]);

it('checks every list item', function (): void {
    $rule = new JsonField(JsonField::LIST, 1024, fn (mixed $item): bool => is_int($item));

    expect(jsonPasses($rule, '[1,2,3]'))->toBeTrue()
        ->and(jsonPasses($rule, '[1,"x"]'))->toBeFalse();
});
