<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\Shipping\Filament\Resources\ShippingPriceResource;

class ListShippingPrices extends ListRecords
{
    protected static string $resource = ShippingPriceResource::class;

    /**
     * No create action, and not because the button is hidden: the resource
     * denies `create` at the authorization funnel.
     *
     * @return array<int, never>
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
