<?php

namespace Eclipse\Catalogue\Filament\Resources\ProductTypeResource\RelationManagers;

use Eclipse\Catalogue\Filament\Resources\PropertyResource;
use Eclipse\Catalogue\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PropertiesRelationManager extends RelationManager
{
    protected static string $relationship = 'properties';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('property_id')
                    ->label('Property')
                    ->options(Property::where('is_active', true)->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                TextInput::make('sort')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers appear first'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Property Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable(),

                IconColumn::make('is_global')
                    ->label('Global')
                    ->boolean(),

                TextColumn::make('max_values')
                    ->label('Max Values')
                    ->formatStateUsing(fn ($state) => $state === 1 ? 'Single' : 'Multiple'),

                IconColumn::make('is_filter')
                    ->label('Filter')
                    ->boolean(),

                TextColumn::make('values_count')
                    ->label('Values')
                    ->counts('values'),
            ])
            ->filters([
                TernaryFilter::make('is_global')
                    ->label('Global Properties'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add property')
                    ->modalHeading('Add Property')
                    ->modalSubmitActionLabel('Add Property')
                    ->modalCancelActionLabel('Cancel')
                    ->extraModalFooterActions(
                        fn (AttachAction $action): array => [
                            $action->makeModalSubmitAction('submitAnother', ['another' => true])
                                ->label('Add Property & Add Another'),
                        ]
                    )
                    ->recordSelectOptionsQuery(function ($query) {
                        $attachedIds = $this->getOwnerRecord()
                            ->properties()
                            ->pluck('pim_property.id');

                        return $query
                            ->where('is_active', true)
                            ->whereNotIn('id', $attachedIds);
                    })
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'code']),
            ])
            ->recordActions([
                Action::make('edit_property')
                    ->label('Edit Property')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record): string => PropertyResource::getUrl('edit', ['record' => $record->id]))
                    ->openUrlInNewTab(),

                DetachAction::make()
                    ->label('Remove')
                    ->modalHeading(fn ($record) => 'Remove '.($record->name ?? 'property'))
                    ->modalSubmitActionLabel('Remove')
                    ->modalCancelActionLabel('Cancel'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove')
                        ->modalHeading('Remove selected')
                        ->modalSubmitActionLabel('Remove')
                        ->modalCancelActionLabel('Cancel'),
                ]),
            ])
            ->defaultSort('pim_product_type_has_property.sort')
            ->reorderable('pim_product_type_has_property.sort')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering) => $action
                    ->button()
                    ->label($isReordering ? 'Disable reordering' : 'Enable reordering')
                    ->icon($isReordering ? 'heroicon-o-x-mark' : 'heroicon-o-arrows-up-down')
                    ->color($isReordering ? 'danger' : 'primary')
            );
    }
}
