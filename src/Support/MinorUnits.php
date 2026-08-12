<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Support;

use Liberu\Ecommerce\Shipping\Data\Money;

/**
 * The one place a decimal string becomes integer minor units, and back.
 *
 * Both directions go through the domain's Money, which does the conversion as
 * string arithmetic: `(int) (19.99 * 100)` is 1998, and a form is exactly where
 * that mistake gets made. No float ever exists on either side of this class.
 */
final class MinorUnits
{
    public static function toMinor(int|string|null $decimal, string $currency): ?int
    {
        if ($decimal === null || trim((string) $decimal) === '') {
            return null;
        }

        return Money::fromDecimalString((string) $decimal, $currency)->minor;
    }

    public static function toDecimal(?int $minor, string $currency = 'XXX'): ?string
    {
        return $minor === null ? null : new Money($minor, $currency)->decimal();
    }

    /** Formats an amount for a table cell or an infolist entry. */
    public static function format(?int $minor, ?string $currency): string
    {
        if ($minor === null || $currency === null) {
            return '—';
        }

        return $currency.' '.new Money($minor, $currency)->decimal();
    }
}
