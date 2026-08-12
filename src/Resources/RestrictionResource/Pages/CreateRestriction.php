<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Liberu\Ecommerce\Shipping\Filament\Resources\RestrictionResource;

class CreateRestriction extends CreateRecord
{
    use SavesRestriction;

    protected static string $resource = RestrictionResource::class;

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveRestriction($data, null);
    }
}
