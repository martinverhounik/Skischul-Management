<?php

use App\Support\Money;

it('reads amounts the way people type them', function (string $input, ?int $cents) {
    expect(Money::parse($input))->toBe($cents);
})->with([
    ['12', 1200],
    ['12,5', 1250],
    ['12,50', 1250],
    ['12.50', 1250],
    ['€ 12,50', 1250],
    ['1.234,50', 123450],
    ['1,234.50', 123450],
    ['1.234', 123400],
    ['0', 0],
    ['', null],
    ['abc', null],
    ['-5', null],
    ['1.2.3', null],
]);

it('shows amounts in euros', function () {
    app()->setLocale('de');
    expect(Money::format(123450))->toContain('1.234,50')->toContain('€')
        ->and(Money::toInput(1250))->toBe('12,50');

    app()->setLocale('en');
    expect(Money::toInput(1250))->toBe('12.50');
});
