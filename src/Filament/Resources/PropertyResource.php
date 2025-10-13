<?php

namespace Eclipse\Catalogue\Filament\Resources;

use Eclipse\Catalogue\Enums\PropertyInputType;
use Eclipse\Catalogue\Enums\PropertyType;
use Eclipse\Catalogue\Filament\Resources\PropertyResource\Pages\CreateProperty;
use Eclipse\Catalogue\Filament\Resources\PropertyResource\Pages\EditProperty;
use Eclipse\Catalogue\Filament\Resources\PropertyResource\Pages\ListProperties;
use Eclipse\Catalogue\Filament\Resources\PropertyResource\RelationManagers\ValuesRelationManager;
use Eclipse\Catalogue\Models\ProductType;
use Eclipse\Catalogue\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class PropertyResource extends Resource
{
    use Translatable;

    protected static ?string $model = Property::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('eclipse-catalogue::property.sections.basic_information'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('eclipse-catalogue::property.fields.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('code')
                            ->label(__('eclipse-catalogue::property.fields.code'))
                            ->helperText(__('eclipse-catalogue::property.help_text.code'))
                            ->regex('/^[a-zA-Z0-9_]*$/')
                            ->unique(ignoreRecord: true),

                        Textarea::make('description')
                            ->label(__('eclipse-catalogue::property.fields.description'))
                            ->rows(3),

                        TextInput::make('internal_name')
                            ->label(__('eclipse-catalogue::property.fields.internal_name'))
                            ->helperText(__('eclipse-catalogue::property.help_text.internal_name'))
                            ->maxLength(255),
                    ])->columns(2),

                Section::make(__('eclipse-catalogue::property.sections.configuration'))
                    ->schema([
                        Toggle::make('is_active')
                            ->label(__('eclipse-catalogue::property.fields.is_active'))
                            ->default(true),

                        Toggle::make('is_global')
                            ->label(__('eclipse-catalogue::property.fields.is_global'))
                            ->helperText(__('eclipse-catalogue::property.help_text.is_global'))
                            ->reactive(),

                        Select::make('type')
                            ->label('Property Type')
                            ->options(fn () => collect(PropertyType::cases())
                                ->mapWithKeys(fn (PropertyType $e) => [$e->value => $e->getLabel()])
                                ->toArray())
                            ->default(PropertyType::LIST->value)
                            ->reactive()
                            ->required(),

                        Select::make('input_type')
                            ->label('Input Type')
                            ->options(fn () => collect(PropertyInputType::cases())
                                ->mapWithKeys(fn (PropertyInputType $e) => [$e->value => $e->getLabel()])
                                ->toArray())
                            ->visible(fn (Get $get) => $get('type') === PropertyType::CUSTOM->value)
                            ->required(fn (Get $get) => $get('type') === PropertyType::CUSTOM->value)
                            ->reactive(),

                        Toggle::make('is_multilang')
                            ->label('Multilingual')
                            ->helperText('Enable translation support for string, text, and file inputs')
                            ->visible(fn (Get $get) => $get('type') === PropertyType::CUSTOM->value && in_array($get('input_type'), [
                                PropertyInputType::STRING->value,
                                PropertyInputType::TEXT->value,
                                PropertyInputType::FILE->value,
                            ]))
                            ->default(false),

                        TextInput::make('max_values')
                            ->label(__('eclipse-catalogue::property.fields.max_values'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(10)
                            ->helperText(__('eclipse-catalogue::property.help_text.max_values'))
                            ->visible(fn (Get $get) => $get('type') === PropertyType::LIST->value || $get('input_type') === PropertyInputType::FILE->value),

                        Toggle::make('enable_sorting')
                            ->label(__('eclipse-catalogue::property.fields.enable_sorting'))
                            ->helperText(__('eclipse-catalogue::property.help_text.enable_sorting'))
                            ->visible(fn (Get $get) => $get('type') === PropertyType::LIST->value),

                        Toggle::make('is_filter')
                            ->label(__('eclipse-catalogue::property.fields.is_filter'))
                            ->helperText(__('eclipse-catalogue::property.help_text.is_filter')),
                    ])->columns(2),

                Section::make(__('eclipse-catalogue::property.sections.product_types'))
                    ->schema([
                        CheckboxList::make('product_types')
                            ->label(__('eclipse-catalogue::property.fields.product_types'))
                            ->relationship('productTypes', 'name')
                            ->options(ProductType::pluck('name', 'id'))
                            ->helperText(__('eclipse-catalogue::property.help_text.product_types'))
                            ->hidden(fn (Get $get) => $get('is_global')),
                    ])
                    ->hidden(fn (Get $get) => $get('is_global')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('eclipse-catalogue::property.table.columns.code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label(__('eclipse-catalogue::property.table.columns.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('internal_name')
                    ->label(__('eclipse-catalogue::property.table.columns.internal_name'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'list' => 'success',
                        'color' => 'info',
                        'custom' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('input_type')
                    ->label('Input Type')
                    ->visible(fn (?Property $record) => $record && $record->isCustomType())
                    ->badge()
                    ->color('info'),

                IconColumn::make('is_multilang')
                    ->label('Multilingual')
                    ->boolean()
                    ->state(fn (?Property $record) => $record?->supportsMultilang())
                    ->visible(fn (?Property $record) => $record && $record->supportsMultilang()),

                IconColumn::make('is_global')
                    ->label(__('eclipse-catalogue::property.table.columns.is_global'))
                    ->boolean(),

                TextColumn::make('max_values')
                    ->label(__('eclipse-catalogue::property.table.columns.max_values'))
                    ->numeric(),

                IconColumn::make('enable_sorting')
                    ->label(__('eclipse-catalogue::property.table.columns.enable_sorting'))
                    ->boolean(),

                IconColumn::make('is_filter')
                    ->label(__('eclipse-catalogue::property.table.columns.is_filter'))
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label(__('eclipse-catalogue::property.table.columns.is_active'))
                    ->boolean(),

                TextColumn::make('values_count')
                    ->label(__('eclipse-catalogue::property.table.columns.values_count'))
                    ->counts('values'),

                TextColumn::make('created_at')
                    ->label(__('eclipse-catalogue::property.table.columns.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->label(__('eclipse-catalogue::property.table.filters.product_type'))
                    ->relationship('productTypes', 'name')
                    ->multiple(),

                SelectFilter::make('type')
                    ->label('Property Type')
                    ->options([
                        PropertyType::LIST->value => PropertyType::LIST->getLabel(),
                        PropertyType::COLOR->value => PropertyType::COLOR->getLabel(),
                        PropertyType::CUSTOM->value => PropertyType::CUSTOM->getLabel(),
                    ]),

                TernaryFilter::make('is_global')
                    ->label(__('eclipse-catalogue::property.table.filters.is_global')),

                TernaryFilter::make('is_active')
                    ->label(__('eclipse-catalogue::property.table.filters.is_active')),

                TernaryFilter::make('is_filter')
                    ->label(__('eclipse-catalogue::property.table.filters.is_filter')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('values')
                        ->label(__('eclipse-catalogue::property.table.actions.values'))
                        ->icon('heroicon-o-list-bullet')
                        ->url(fn (Property $record): string => PropertyValueResource::getUrl('index', ['property' => $record->id]))
                        ->visible(fn (Property $record): bool => in_array($record->type, [PropertyType::LIST->value, PropertyType::COLOR->value], true)),
                    EditAction::make(),
                    DeleteAction::make(),
                ])->label('Actions'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (Property $record): ?string => in_array($record->type, [PropertyType::LIST->value, PropertyType::COLOR->value], true) ? PropertyValueResource::getUrl('index', ['property' => $record->id]) : null)
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            ValuesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProperties::route('/'),
            'create' => CreateProperty::route('/create'),
            'edit' => EditProperty::route('/{record}/edit'),
        ];
    }
}
