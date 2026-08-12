<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Liberu\Ecommerce\Shipping\Filament\Resources\ServiceLevelResource;
use Liberu\Ecommerce\Shipping\Filament\Support\Tenant;

class CreateServiceLevel extends CreateRecord
{
    protected static string $resource = ServiceLevelResource::class;

    /**
     * The tenant is taken from the panel, never from the form: a tenant id a
     * browser can set is a tenant id a browser can change.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Tenant::current();

        return $data;
    }
}
