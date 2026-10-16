<?php

declare(strict_types=1);

use App\Support\ProjectColor;

// Same values as widget/tests/unit/theme.test.ts.
it('derives the text color and contrast', function (string $input, string $primary, string $on, string $contrast): void {
    $color = ProjectColor::fromInput($input);

    expect($color->primary)->toBe($primary)
        ->and($color->onPrimary)->toBe($on)
        ->and($color->formattedContrast())->toBe($contrast);
})->with([
    'black' => ['black', '#000000', '#FFFFFF', '21.00:1'],
    'white' => ['white', '#FFFFFF', '#101F3C', '16.36:1'],
    'orange' => ['#ED813A', '#ED813A', '#101F3C', '6.08:1'],
    'green' => ['#0A7C66', '#0A7C66', '#FFFFFF', '5.14:1'],
]);

it('reports the rejected alternative for orange', function (): void {
    expect(round(ProjectColor::contrast('#FFFFFF', '#ED813A'), 2))->toBe(2.69);
});

it('uses a divider and a dark button for white', function (): void {
    $white = ProjectColor::fromInput('white');

    expect($white->divider)->toBeTrue()
        ->and($white->strong)->toBe(ProjectColor::INK)
        ->and($white->onStrong)->toBe('#FFFFFF')
        ->and(ProjectColor::fromInput('black')->divider)->toBeFalse();
});

it('adjusts mid tones until buttons reach 4.5:1', function (string $hex): void {
    $color = ProjectColor::fromInput($hex);

    expect($color->strongContrast)->toBeGreaterThanOrEqual(4.5)
        ->and($color->strong)->not->toBe($color->primary);
})->with(['#808080', '#E0218A', '#2F80ED']);

it('keeps colors with enough contrast unchanged', function (): void {
    $color = ProjectColor::fromInput('#0A7C66');

    expect($color->strong)->toBe('#0A7C66')->and($color->strongContrast)->toBe(5.14);
});

it('parses color input', function (string $input, ?string $expected): void {
    expect(ProjectColor::parse($input))->toBe($expected);
})->with([
    ['black', '#000000'],
    [' WHITE ', '#FFFFFF'],
    ['#0a7c66', '#0A7C66'],
    ['0A7C66', '#0A7C66'],
    ['#abc', '#AABBCC'],
    ['#12345', null],
    ['#GGGGGG', null],
    ['red', null],
    ['', null],
]);

it('rejects invalid input', function (): void {
    ProjectColor::fromInput('blau');
})->throws(InvalidArgumentException::class);

it('exports all derived tokens', function (): void {
    expect(ProjectColor::fromInput('#ED813A')->toArray())->toMatchArray([
        'primary' => '#ED813A',
        'on_primary' => '#101F3C',
        'contrast' => 6.08,
        'divider' => false,
    ]);
});
