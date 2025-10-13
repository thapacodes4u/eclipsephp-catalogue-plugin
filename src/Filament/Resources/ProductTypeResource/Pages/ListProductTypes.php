<?php

namespace Eclipse\Catalogue\Filament\Resources\ProductTypeResource\Pages;

use Eclipse\Catalogue\Filament\Resources\ProductTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class ListProductTypes extends ListRecords
{
    use Translatable;

    protected static string $resource = ProductTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
