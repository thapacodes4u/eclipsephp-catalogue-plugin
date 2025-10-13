<?php

namespace Eclipse\Catalogue\Filament\Resources;

use Eclipse\Catalogue\Enums\StructuredData\ItemAvailability;
use Eclipse\Catalogue\Filament\Resources\ProductStatusResource\Pages\CreateProductStatus;
use Eclipse\Catalogue\Filament\Resources\ProductStatusResource\Pages\EditProductStatus;
use Eclipse\Catalogue\Filament\Resources\ProductStatusResource\Pages\ListProductStatuses;
use Eclipse\Catalogue\Models\ProductStatus;
use Eclipse\Catalogue\Support\LabelType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class ProductStatusResource extends Resource
{
    use Translatable;

    protected static ?string $model = ProductStatus::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $slug = 'product-statuses';

    protected static ?string $navigationLabel = null;

    public static function getNavigationLabel(): string
    {
        return __('eclipse-catalogue::product-status.navigation_label');
    }

    public static function getModelLabel(): string
    {
        return __('eclipse-catalogue::product-status.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('eclipse-catalogue::product-status.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('eclipse-catalogue::product-status.singular'))
                ->schema([
                    TextInput::make('title')->label(__('eclipse-catalogue::product-status.fields.title'))
                        ->helperText(__('eclipse-catalogue::product-status.help_text.title'))
                        ->required()->maxLength(255),
                    Grid::make(3)->schema([
                        TextInput::make('code')->label(__('eclipse-catalogue::product-status.fields.code'))
                            ->helperText(__('eclipse-catalogue::product-status.help_text.code'))
                            ->nullable()
                            ->maxLength(20)
                            ->unique(
                                table: 'pim_product_statuses',
                                column: 'code',
                                ignoreRecord: true,
                                modifyRuleUsing: function ($rule, $livewire) {
                                    $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                                    if ($tenantFK) {
                                        $currentTenant = Filament::getTenant();
                                        if ($currentTenant) {
                                            $rule->where($tenantFK, $currentTenant->id);
                                        }
                                    }

                                    return $rule;
                                }
                            )
                            ->validationMessages([
                                'unique' => __('eclipse-catalogue::product-status.validation.code_unique'),
                            ]),
                        Select::make('label_type')
                            ->label(__('eclipse-catalogue::product-status.fields.label_type'))
                            ->helperText(__('eclipse-catalogue::product-status.help_text.label_type'))
                            ->options(LabelType::options())
                            ->default('gray')
                            ->required(),
                        TextInput::make('priority')->label(__('eclipse-catalogue::product-status.fields.priority'))
                            ->helperText(__('eclipse-catalogue::product-status.help_text.priority'))
                            ->numeric()->required(),
                    ]),
                    Textarea::make('description')->label(__('eclipse-catalogue::product-status.fields.description'))
                        ->helperText(__('eclipse-catalogue::product-status.help_text.description'))
                        ->rows(3),
                ])->columns(1),
            Section::make(__('eclipse-catalogue::product-status.sections.visibility_rules'))->schema([
                Grid::make(3)->schema([
                    Toggle::make('shown_in_browse')->label(__('eclipse-catalogue::product-status.fields.shown_in_browse'))
                        ->helperText(__('eclipse-catalogue::product-status.help_text.shown_in_browse'))
                        ->default(true),
                    Toggle::make('allow_price_display')->label(__('eclipse-catalogue::product-status.fields.allow_price_display'))
                        ->helperText(__('eclipse-catalogue::product-status.help_text.allow_price_display'))
                        ->default(true)
                        ->live(),
                    Toggle::make('allow_sale')->label(__('eclipse-catalogue::product-status.fields.allow_sale'))
                        ->helperText(__('eclipse-catalogue::product-status.help_text.allow_sale'))
                        ->default(true)
                        ->disabled(fn ($get) => $get('allow_price_display') === false),
                    Toggle::make('is_default')->label(__('eclipse-catalogue::product-status.fields.is_default'))
                        ->helperText(__('eclipse-catalogue::product-status.help_text.is_default'))
                        ->default(false),
                    Toggle::make('skip_stock_qty_check')->label(__('eclipse-catalogue::product-status.fields.skip_stock_qty_check'))
                        ->helperText(__('eclipse-catalogue::product-status.help_text.skip_stock_qty_check'))
                        ->default(false),
                    Select::make('sd_item_availability')->label(__('eclipse-catalogue::product-status.fields.sd_item_availability'))
                        ->helperText(new HtmlString(__('eclipse-catalogue::product-status.help_text.sd_item_availability')))
                        ->options(ItemAvailability::class)
                        ->searchable()
                        ->required(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(__('eclipse-catalogue::product-status.fields.code'))->searchable(),
            BadgeColumn::make('title')->label(__('eclipse-catalogue::product-status.fields.title'))
                ->formatStateUsing(fn ($state) => is_array($state) ? ($state[app()->getLocale()] ?? reset($state)) : $state)
                ->color(fn (ProductStatus $record) => $record->label_type)
                ->extraAttributes(fn (ProductStatus $record) => ['class' => LabelType::badgeClass($record->label_type)]),
            IconColumn::make('shown_in_browse')->label(__('eclipse-catalogue::product-status.fields.shown_in_browse'))->boolean(),
            IconColumn::make('allow_price_display')->label(__('eclipse-catalogue::product-status.fields.allow_price_display'))->boolean(),
            IconColumn::make('allow_sale')->label(__('eclipse-catalogue::product-status.fields.allow_sale'))->boolean(),
            IconColumn::make('is_default')->label(__('eclipse-catalogue::product-status.fields.is_default'))->boolean(),
            TextColumn::make('priority')->label(__('eclipse-catalogue::product-status.fields.priority'))->numeric(),
        ])->filters([
            TernaryFilter::make('shown_in_browse')->label(__('eclipse-catalogue::product-status.fields.shown_in_browse')),
        ])->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductStatuses::route('/'),
            'create' => CreateProductStatus::route('/create'),
            'edit' => EditProductStatus::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        // Filter by current tenant if tenancy is enabled
        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
        if ($tenantFK) {
            $currentTenant = Filament::getTenant();
            if ($currentTenant) {
                $query->where($tenantFK, $currentTenant->id);
            }
        }

        return $query;
    }
}
