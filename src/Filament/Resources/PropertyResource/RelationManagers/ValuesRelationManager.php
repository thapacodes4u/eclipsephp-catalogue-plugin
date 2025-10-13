<?php

namespace Eclipse\Catalogue\Filament\Resources\PropertyResource\RelationManagers;

use Eclipse\Catalogue\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\RelationManagers\Concerns\Translatable;

class ValuesRelationManager extends RelationManager
{
    use Translatable;

    protected static string $relationship = 'values';

    protected static ?string $recordTitleAttribute = 'value';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('value')
                    ->translatable()
                    ->label(__('eclipse-catalogue::property-value.fields.value'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('info_url')
                    ->label(__('eclipse-catalogue::property-value.fields.info_url'))
                    ->helperText(__('eclipse-catalogue::property-value.help_text.info_url'))
                    ->url()
                    ->maxLength(255),

                FileUpload::make('image')
                    ->label(__('eclipse-catalogue::property-value.fields.image'))
                    ->helperText(__('eclipse-catalogue::property-value.help_text.image'))
                    ->image()
                    ->formatStateUsing(function ($state) {
                        if (is_string($state) || $state === null) {
                            return $state;
                        }

                        if (is_array($state)) {
                            $locale = app()->getLocale();
                            $byLocale = $state[$locale] ?? null;
                            if (is_string($byLocale) && $byLocale !== '') {
                                return $byLocale;
                            }

                            foreach ($state as $value) {
                                if (is_string($value) && $value !== '') {
                                    return $value;
                                }
                            }

                            return null;
                        }

                        return null;
                    })
                    ->nullable()
                    ->disk('public')
                    ->directory('property-values'),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        /** @var Property $property */
        $property = $this->getOwnerRecord();

        $table = $table
            ->columns([
                TextColumn::make('value')
                    ->label(__('eclipse-catalogue::property-value.table.columns.value'))
                    ->searchable()
                    ->sortable(),

                ImageColumn::make('image')
                    ->label(__('eclipse-catalogue::property-value.table.columns.image'))
                    ->disk('public')
                    ->size(40),

                TextColumn::make('info_url')
                    ->label(__('eclipse-catalogue::property-value.table.columns.info_url'))
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('products_count')
                    ->label(__('eclipse-catalogue::property-value.table.columns.products_count'))
                    ->counts('products'),

                TextColumn::make('created_at')
                    ->label(__('eclipse-catalogue::property-value.table.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->deferLoading()
            ->headerActions([
                LocaleSwitcher::make(),
                CreateAction::make()
                    ->modalWidth('lg')
                    ->modalHeading(__('eclipse-catalogue::property-value.modal.create_heading')),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalWidth('lg')
                    ->modalHeading(__('eclipse-catalogue::property-value.modal.edit_heading')),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        if ($property->enable_sorting) {
            $table = $table
                ->reorderable('sort')
                ->defaultSort('sort')
                ->reorderRecordsTriggerAction(
                    fn (Action $action, bool $isReordering) => $action
                        ->button()
                        ->label($isReordering ? 'Disable reordering' : 'Enable reordering')
                        ->icon($isReordering ? 'heroicon-o-x-mark' : 'heroicon-o-arrows-up-down')
                        ->color($isReordering ? 'danger' : 'primary')
                        ->extraAttributes(['class' => 'reorder-trigger'])
                );
        } else {
            $table = $table->defaultSort('value');
        }

        return $table;
    }
}
