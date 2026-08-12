<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Pages;

use RuntimeException;

/**
 * Thrown to roll the preview's transaction back.
 *
 * Quoting is a write: it records an offered price per option, with its parcels.
 * An operator checking their own rules must not leave priced offers behind, so
 * the preview runs inside a transaction it deliberately aborts. Nothing else
 * catches this class.
 */
final class PreviewIsNotAQuote extends RuntimeException {}
