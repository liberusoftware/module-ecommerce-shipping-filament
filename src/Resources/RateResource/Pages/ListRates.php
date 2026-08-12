<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource;

class ListRates extends ListRecords
{
    protected static string $resource = RateResource::class;

    /** @return array<int, CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
