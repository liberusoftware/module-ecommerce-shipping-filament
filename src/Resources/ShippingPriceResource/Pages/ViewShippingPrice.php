<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource;

class ViewShippingPrice extends ViewRecord
{
    protected static string $resource = ShippingPriceResource::class;

    /** @return array<int, never> */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
