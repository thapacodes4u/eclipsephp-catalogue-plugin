<?php

namespace Eclipse\Catalogue\Filament\Resources\CategoryResource\Pages;

use Eclipse\Catalogue\Filament\Resources\CategoryResource;
use Illuminate\Database\Eloquent\Model;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use SolutionForest\FilamentTree\Concern\TreeRecords\Translatable;
use SolutionForest\FilamentTree\Resources\Pages\TreePage as BasePage;

class SortingCategory extends BasePage
{
    use Translatable;

    protected static string $resource = CategoryResource::class;

    protected static int $maxDepth = 6;

    public function getTitle(): string
    {
        return __('eclipse-catalogue::categories.sorting');
    }

    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
            $this->getCreateAction()
                ->translateLabel()
                ->label(__('eclipse-catalogue::categories.actions.create'))
                ->icon('heroicon-o-plus-circle'),
        ];
    }

    public function getTreeRecordTitle(?Model $record = null): string
    {
        if (! $record) {
            return '';
        }

        return $record->name;
    }

    protected function hasDeleteAction(): bool
    {
        return false;
    }

    protected function hasEditAction(): bool
    {
        return true;
    }

    protected function hasViewAction(): bool
    {
        return false;
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getTreeRecordIcon(?Model $record = null): ?string
    {
        return null;
    }
}
