<?php

namespace Eclipse\Catalogue\Filament\Resources\GroupResource\Pages;

use Eclipse\Catalogue\Filament\Resources\GroupResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateGroup extends CreateRecord
{
    protected static string $resource = GroupResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $currentTenant = Filament::getTenant();
        if ($currentTenant) {
            $data['site_id'] = $currentTenant->id;
        }

        return $data;
    }
}
