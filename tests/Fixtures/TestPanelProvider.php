<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Liberu\Ecommerce\Shipping\Filament\ShippingFilamentPlugin;

/**
 * The panel this package's resources need in order to be resources at all.
 *
 * This package ships a plugin, never a panel — the host composes one. So the
 * suite composes the smallest panel that attaches the plugin the manifest
 * declares. The id is `admin` only because that is the panel
 * `presentation.filament.admin` names.
 */
final class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([ShippingFilamentPlugin::make()]);
    }
}
