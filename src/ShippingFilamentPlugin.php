<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Liberu\Ecommerce\Shipping\Filament\Pages\RatePreview;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource;
use Liberu\Ecommerce\Shipping\Filament\Widgets\ShippingCoverageOverview;

/**
 * Attached per panel by the application. Nothing here registers globally.
 */
final class ShippingFilamentPlugin implements Plugin
{
    /** @var array<int, class-string> */
    public const RESOURCES = [
        ZoneResource::class,
        ServiceLevelResource::class,
        RateResource::class,
        RestrictionResource::class,
        ShippingPriceResource::class,
    ];

    /** @var array<int, class-string> */
    public const PAGES = [
        RatePreview::class,
    ];

    /** @var array<int, class-string> */
    public const WIDGETS = [
        ShippingCoverageOverview::class,
    ];

    public static function make(): self
    {
        return new self();
    }

    public function getId(): string
    {
        return 'ecommerce-shipping';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources(self::RESOURCES)
            ->pages(self::PAGES)
            ->widgets(self::WIDGETS);
    }

    public function boot(Panel $panel): void {}
}
