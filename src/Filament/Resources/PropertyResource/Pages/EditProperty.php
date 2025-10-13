<?php

namespace Eclipse\Catalogue\Filament\Resources\PropertyResource\Pages;

use Eclipse\Catalogue\Enums\PropertyType;
use Eclipse\Catalogue\Filament\Resources\PropertyResource;
use Eclipse\Catalogue\Filament\Resources\PropertyResource\RelationManagers\ValuesRelationManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;

class EditProperty extends EditRecord
{
    use Translatable;

    protected static string $resource = PropertyResource::class;

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        $managers = [];

        if ($this->getRecord() && $this->getRecord()->type === PropertyType::LIST->value) {
            $managers[] = ValuesRelationManager::class;
        }

        return $managers;
    }
}
