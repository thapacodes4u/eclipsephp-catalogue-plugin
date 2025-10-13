<?php

namespace Eclipse\Catalogue\Filament\Resources\GroupResource\Pages;

use Eclipse\Catalogue\Filament\Resources\GroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGroup extends EditRecord
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
