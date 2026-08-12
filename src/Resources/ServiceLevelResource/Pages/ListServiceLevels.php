<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource;

class ListServiceLevels extends ListRecords
{
    protected static string $resource = ServiceLevelResource::class;

    /** @return array<int, CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
