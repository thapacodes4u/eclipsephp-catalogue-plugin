<?php

namespace Eclipse\Catalogue\Filament\Resources\CategoryResource\Pages;

use Eclipse\Catalogue\Filament\Resources\CategoryResource;
use Eclipse\Common\Foundation\Pages\HasScoutSearch;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListCategories extends ListRecords
{
    use HasScoutSearch, Translatable;

    protected static string $resource = CategoryResource::class;

    protected Width|string|null $maxContentWidth = Width::Full->value;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            Action::make('sorting')
                ->label(__('eclipse-catalogue::categories.actions.sorting'))
                ->icon('heroicon-o-arrows-up-down')
                ->color('gray')
                ->url(fn () => self::$resource::getUrl('sorting')),
            CreateAction::make()
                ->label(__('eclipse-catalogue::categories.actions.create'))
                ->icon('heroicon-o-plus-circle'),
        ];
    }
}
