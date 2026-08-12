<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource;

class ListRestrictions extends ListRecords
{
    protected static string $resource = RestrictionResource::class;

    /** @return array<int, CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
