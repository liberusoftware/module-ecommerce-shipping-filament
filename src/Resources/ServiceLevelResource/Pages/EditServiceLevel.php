<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource;

class EditServiceLevel extends EditRecord
{
    protected static string $resource = ServiceLevelResource::class;

    /** @return array<int, DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
