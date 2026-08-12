<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource;
use Liberu\Ecommerce\Shipping\Filament\Support\MinorUnits;
use Liberu\Ecommerce\Shipping\Models\RateBand;

class EditRate extends EditRecord
{
    use SavesRate;

    protected static string $resource = RateResource::class;

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
        $currency = (string) $record->currency;

        $data['amount'] = MinorUnits::toDecimal($record->amount_minor, $currency);
        $data['free_above_subtotal'] = MinorUnits::toDecimal($record->free_above_subtotal_minor, $currency);
        $data['bands'] = $record->bands
            ->map(static fn (RateBand $band): array => [
                'lower_bound' => $band->lower_bound,
                'upper_bound' => $band->upper_bound,
                'is_unbounded' => $band->is_unbounded,
                'amount' => MinorUnits::toDecimal($band->amount_minor, $currency),
            ])
            ->values()
            ->all();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return $this->saveRate($data, (int) $record->getKey());
    }
}
