<?php

namespace Eclipse\Catalogue\Filament\Resources;

use Eclipse\Catalogue\Enums\BackgroundType;
use Eclipse\Catalogue\Enums\GradientDirection;
use Eclipse\Catalogue\Enums\GradientStyle;
use Eclipse\Catalogue\Filament\Resources\PropertyValueResource\Pages\ListPropertyValues;
use Eclipse\Catalogue\Models\Property;
use Eclipse\Catalogue\Models\PropertyValue;
use Eclipse\Catalogue\Values\Background;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;
use Log;
use Throwable;

class PropertyValueResource extends Resource
{
    use Translatable;

    protected static ?string $model = PropertyValue::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    public static function form(Schema $form): Schema
    {
        $schema = [
            Hidden::make('property_id')
                ->default(fn () => request()->has('property') ? (int) request('property') : null),

            TextInput::make('value')
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
        ];

        if (request()->has('property')) {
            $prop = Property::find((int) request('property'));
            if ($prop && $prop->isColorType()) {
                $colorGroup = static::buildColorGroupSchema();
                array_splice($schema, 1, 0, $colorGroup);
            }
        }

        return $form->components($schema)->columns(1);
    }

    public static function table(Table $table): Table
    {
        $table = $table
            ->columns([
                TextColumn::make('value')
                    ->label(__('eclipse-catalogue::property-value.table.columns.value'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('color_swatch')
                    ->label('Color')
                    ->state(fn ($record) => $record->getColor())
                    ->formatStateUsing(function ($state) {
                        $base = 'display:inline-block;width:20px;height:20px;border-radius:4px;outline:1px solid rgba(0,0,0,.15);';
                        $bg = is_string($state) ? $state : '';

                        return '<span style="'.$base.$bg.'"></span>';
                    })
                    ->html(),

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
                SelectFilter::make('property')
                    ->label(__('eclipse-catalogue::property-value.table.filters.property'))
                    ->relationship('property', 'name')
                    ->default(fn () => request('property')),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->modalWidth('lg')
                        ->modalHeading(__('eclipse-catalogue::property-value.modal.edit_heading'))
                        ->schema(function (Schema $form) {
                            $schema = [
                                TextInput::make('value')
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
                                    ->nullable()
                                    ->disk('public')
                                    ->directory('property-values'),
                            ];

                            $prop = null;
                            if (request()->has('property')) {
                                $prop = Property::find((int) request('property'));
                            } elseif ($record = $form->getModelInstance()) {
                                if (method_exists($record, 'property')) {
                                    $prop = $record->property;
                                }
                            }

                            if ($prop && $prop->isColorType()) {
                                $colorGroup = static::buildColorGroupSchema();
                                array_splice($schema, 1, 0, $colorGroup);
                            }

                            return $form->components($schema)->columns(1);
                        }),
                    Action::make('merge')
                        ->label(__('eclipse-catalogue::property-value.table.actions.merge'))
                        ->icon('heroicon-o-arrow-uturn-right')
                        ->modalHeading(__('eclipse-catalogue::property-value.modal.merge_heading'))
                        ->schema(function (PropertyValue $record) {
                            return [
                                Placeholder::make('current_value')
                                    ->label(__('eclipse-catalogue::property-value.modal.merge_from_label'))
                                    ->content($record->value),
                                Select::make('target_id')
                                    ->label(__('eclipse-catalogue::property-value.modal.merge_to_label'))
                                    ->required()
                                    ->options(
                                        PropertyValue::query()
                                            ->where('property_id', $record->property_id)
                                            ->whereKeyNot($record->id)
                                            ->orderBy('value')
                                            ->pluck('value', 'id')
                                    ),
                                Placeholder::make('merge_helper')
                                    ->label('')
                                    ->content(__('eclipse-catalogue::property-value.modal.merge_helper'))
                                    ->columnSpanFull(),
                            ];
                        })
                        ->modalSubmitActionLabel(__('eclipse-catalogue::property-value.modal.merge_submit_label'))
                        ->modalCancelActionLabel(__('eclipse-catalogue::property-value.modal.cancel_label'))
                        ->requiresConfirmation()
                        ->modalIcon('heroicon-o-question-mark-circle')
                        ->modalHeading(__('eclipse-catalogue::property-value.modal.merge_confirm_title'))
                        ->modalDescription(__('eclipse-catalogue::property-value.modal.merge_confirm_body'))
                        ->action(function (PropertyValue $record, array $data) {
                            try {
                                $result = $record->mergeInto((int) $data['target_id']);

                                Notification::make()
                                    ->title(__('eclipse-catalogue::property-value.messages.merged_title'))
                                    ->body(__('eclipse-catalogue::property-value.messages.merged_body', ['affected' => $result['affected_products']]))
                                    ->success()
                                    ->send();
                            } catch (Throwable $e) {
                                Log::error('Merge property values failed', ['exception' => $e]);
                                Notification::make()
                                    ->title(__('eclipse-catalogue::property-value.messages.merged_error_title'))
                                    ->body(__('eclipse-catalogue::property-value.messages.merged_error_body'))
                                    ->danger()
                                    ->send();
                                throw $e;
                            }
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);

        return $table;
    }

    public static function buildColorGroupSchema(): array
    {
        return [
            Group::make()
                ->statePath('color')
                ->dehydrated(true)
                ->afterStateHydrated(function (Group $component, $state) {
                    if (is_string($state)) {
                        $decoded = json_decode($state, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $component->state($decoded);
                        }
                    }
                })
                ->dehydrateStateUsing(fn ($state) => $state)
                ->schema([
                    Radio::make('type')
                        ->options(fn () => collect(BackgroundType::cases())
                            ->mapWithKeys(fn (BackgroundType $e) => [$e->value => $e->getLabel()])
                            ->toArray())
                        ->default(BackgroundType::NONE->value)
                        ->live(),
                    ColorPicker::make('color')
                        ->visible(fn (Get $get) => $get('type') === 's')
                        ->live(),
                    Grid::make()
                        ->columns(4)
                        ->visible(fn (Get $get) => $get('type') === 'g')
                        ->schema([
                            ColorPicker::make('color_start')->columnSpan(2)->live(),
                            ColorPicker::make('color_end')->columnSpan(2)->live(),
                            Select::make('gradient_direction')
                                ->options(fn () => collect(GradientDirection::cases())
                                    ->mapWithKeys(fn (GradientDirection $e) => [$e->value => $e->getLabel()])
                                    ->toArray())
                                ->default(GradientDirection::BOTTOM->value)->columnSpan(2)->live(),
                            Radio::make('gradient_style')
                                ->options(fn () => collect(GradientStyle::cases())
                                    ->mapWithKeys(fn (GradientStyle $e) => [$e->value => $e->getLabel()])
                                    ->toArray())
                                ->inline()
                                ->inlineLabel(false)
                                ->default(GradientStyle::SHARP->value)
                                ->columnSpan(2)
                                ->live(),
                        ]),
                    ViewField::make('preview')
                        ->view('eclipse-catalogue::components.color-preview')
                        ->visible(function (Get $get) {
                            $bg = Background::fromForm([
                                'type' => $get('type'),
                                'color' => $get('color'),
                                'color_start' => $get('color_start'),
                                'color_end' => $get('color_end'),
                                'gradient_direction' => $get('gradient_direction'),
                                'gradient_style' => $get('gradient_style'),
                            ]);

                            return $bg->hasRenderableCss();
                        })
                        ->viewData(function (Get $get) {
                            $bg = Background::fromForm([
                                'type' => $get('type'),
                                'color' => $get('color'),
                                'color_start' => $get('color_start'),
                                'color_end' => $get('color_end'),
                                'gradient_direction' => $get('gradient_direction'),
                                'gradient_style' => $get('gradient_style'),
                            ]);

                            return ['style' => $bg->toCss(), 'isMulti' => $bg->isMulticolor()];
                        })
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropertyValues::route('/'),
        ];
    }

    /**
     * Attributes stored as JSON translations on the model.
     */
    public static function getTranslatableAttributes(): array
    {
        return [
            'value',
            'info_url',
            'image',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (request()->has('property')) {
            $query->where('property_id', request('property'));
        }

        return $query;
    }
}
