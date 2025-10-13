<?php

namespace Eclipse\Catalogue\Filament\Resources;

use Eclipse\Catalogue\Filament\Resources\PriceListResource\Pages\CreatePriceList;
use Eclipse\Catalogue\Filament\Resources\PriceListResource\Pages\EditPriceList;
use Eclipse\Catalogue\Filament\Resources\PriceListResource\Pages\ListPriceLists;
use Eclipse\Catalogue\Models\PriceList;
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

class PriceListResource extends Resource
{
    protected static ?string $model = PriceList::class;

    protected static ?string $slug = 'price-lists';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('eclipse-catalogue::price-list.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('eclipse-catalogue::price-list.plural');
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

                TextColumn::make('currency.name')
                    ->label(__('eclipse-catalogue::price-list.table.columns.currency'))
                    ->sortable(),

                IconColumn::make('tax_included')
                    ->label(__('eclipse-catalogue::price-list.table.columns.tax_included'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('eclipse-catalogue::price-list.table.columns.is_active'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label(__('eclipse-catalogue::price-list.table.columns.is_default'))
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_default_purchase')
                    ->label(__('eclipse-catalogue::price-list.table.columns.is_default_purchase'))
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('eclipse-catalogue::price-list.table.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label(__('eclipse-catalogue::price-list.table.columns.updated_at'))
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

    public static function getPages(): array
    {
        return [
            'index' => ListPriceLists::route('/'),
            'create' => CreatePriceList::route('/create'),
            'edit' => EditPriceList::route('/{record}/edit'),
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
