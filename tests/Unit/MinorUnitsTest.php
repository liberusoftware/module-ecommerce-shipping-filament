<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Filament\Support\MinorUnits;

it('converts a decimal string to minor units by string arithmetic, not by multiplying a float', function (): void {
    expect(MinorUnits::toMinor('19.99', 'GBP'))->toBe(1999)
        ->and(MinorUnits::toMinor('19.99', 'GBP'))->not->toBe((int) (19.99 * 100));
});

it('keeps every decimal a form can produce exact', function (string $decimal, int $minor): void {
    expect(MinorUnits::toMinor($decimal, 'GBP'))->toBe($minor);
})->with([
    ['0', 0],
    ['0.05', 5],
    ['4.95', 495],
    ['4.5', 450],
    ['45', 4500],
    ['1234.56', 123456],
    ['-2.50', -250],
]);

it('treats an empty field as nothing entered rather than as zero', function (): void {
    expect(MinorUnits::toMinor(null, 'GBP'))->toBeNull()
        ->and(MinorUnits::toMinor('', 'GBP'))->toBeNull()
        ->and(MinorUnits::toMinor('   ', 'GBP'))->toBeNull();
});

it('round-trips through the display form without drifting', function (int $minor): void {
    expect(MinorUnits::toMinor(MinorUnits::toDecimal($minor, 'GBP'), 'GBP'))->toBe($minor);
})->with([0, 5, 495, 1999, 123456]);

it('renders an amount with its currency, and an absent one as a dash', function (): void {
    expect(MinorUnits::format(1999, 'GBP'))->toBe('GBP 19.99')
        ->and(MinorUnits::format(null, 'GBP'))->toBe('—')
        ->and(MinorUnits::format(1999, null))->toBe('—');
});

it('refuses a float at the type boundary, because a float is how a rounding defect gets in', function (): void {
    expect(fn () => MinorUnits::toMinor(19.99, 'GBP'))->toThrow(TypeError::class);
});
