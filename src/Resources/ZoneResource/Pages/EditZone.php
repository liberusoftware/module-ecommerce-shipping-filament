<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Shipping\Filament\Resources\ZoneResource;
use Liberu\Ecommerce\Shipping\Models\ZoneTerritory;

class EditZone extends EditRecord
{
    use SavesZone;

    protected static string $resource = ZoneResource::class;

    /** @return array<int, DeleteAction> */
    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        $data['territories'] = $record->territories
            ->map(static fn (ZoneTerritory $territory): array => [
                'country_code' => $territory->country_code,
                'subdivision_code' => $territory->subdivision_code,
                'postcode_prefix' => $territory->postcode_prefix,
            ])
            ->values()
            ->all();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveZone($data, (int) $record->getKey());
    }
}
