<?php

namespace TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource\Pages;

use Filament\Actions\EditAction;
use Filament\Support\Enums\Width;
use TomatoPHP\FilamentTenancy\Filament\Resources\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTenant extends ViewRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
