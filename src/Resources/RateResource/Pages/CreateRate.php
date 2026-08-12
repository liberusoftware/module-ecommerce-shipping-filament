<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\RateResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Shipping\Filament\Resources\RateResource;

class CreateRate extends CreateRecord
{
    use SavesRate;

    protected static string $resource = RateResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveRate($data, null);
    }
}
