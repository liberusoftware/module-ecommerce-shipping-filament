<?php

declare(strict_types=1);

use Liberu\Ecommerce\Shipping\Filament\Tests\TestCase;
use Liberu\PackageTestbench\PackageTestCase;

// Only the feature suite needs the panel, a database and an actor; the unit
// suite boots this package and nothing else.
pest()->extend(PackageTestCase::class)->in('Unit');
pest()->extend(TestCase::class)->in('Feature');
