<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource;

class EditRestriction extends EditRecord
{
    use SavesRestriction;

    protected static string $resource = RestrictionResource::class;

    /** @return array<int, DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveRestriction($data, (int) $record->getKey());
    }
}
