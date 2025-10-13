<?php

namespace Eclipse\Catalogue\Filament\Resources;

use Eclipse\Catalogue\Filament\Resources\ProductTypeResource\Pages\CreateProductType;
use Eclipse\Catalogue\Filament\Resources\ProductTypeResource\Pages\EditProductType;
use Eclipse\Catalogue\Filament\Resources\ProductTypeResource\Pages\ListProductTypes;
use Eclipse\Catalogue\Filament\Resources\ProductTypeResource\RelationManagers\PropertiesRelationManager;
use Eclipse\Catalogue\Models\ProductType;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class ProductTypeResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductType::class;

    protected static ?string $slug = 'product-types';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('eclipse-catalogue::product-type.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('eclipse-catalogue::product-type.plural');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('eclipse-catalogue::product-type.table.columns.is_active'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label(__('eclipse-catalogue::product-type.table.columns.is_default'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('eclipse-catalogue::product-type.table.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('eclipse-catalogue::product-type.table.columns.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PropertiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductTypes::route('/'),
            'create' => CreateProductType::route('/create'),
            'edit' => EditProductType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
