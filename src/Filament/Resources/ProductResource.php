<?php

namespace Eclipse\Catalogue\Filament\Resources;

use Eclipse\Catalogue\Enums\PropertyInputType;
use Eclipse\Catalogue\Filament\Filters\CustomPropertyConstraint;
use Eclipse\Catalogue\Filament\Forms\Components\ImageManager;
use Eclipse\Catalogue\Filament\Resources\ProductResource\Pages\CreateProduct;
use Eclipse\Catalogue\Filament\Resources\ProductResource\Pages\EditProduct;
use Eclipse\Catalogue\Filament\Resources\ProductResource\Pages\ListProducts;
use Eclipse\Catalogue\Filament\Resources\ProductResource\Pages\ViewProduct;
use Eclipse\Catalogue\Filament\Tables\Actions\BulkUpdateProductsAction;
use Eclipse\Catalogue\Forms\Components\GenericTenantFieldsComponent;
use Eclipse\Catalogue\Forms\Components\InlineTranslatableField;
use Eclipse\Catalogue\Models\Category;
use Eclipse\Catalogue\Models\Group;
use Eclipse\Catalogue\Models\Product;
use Eclipse\Catalogue\Models\ProductStatus;
use Eclipse\Catalogue\Models\ProductType;
use Eclipse\Catalogue\Models\Property;
use Eclipse\Catalogue\Support\LabelType;
use Eclipse\Catalogue\Traits\HandlesTenantData;
use Eclipse\Catalogue\Traits\HasTenantFields;
use Eclipse\World\Models\Country;
use Eclipse\World\Models\TariffCode;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use LaraZeus\SpatieTranslatable\Resources\Concerns\Translatable;

class ProductResource extends Resource
{
    use HandlesTenantData, HasTenantFields, Translatable;

    protected static ?string $model = Product::class;

    protected static ?string $slug = 'products';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Catalogue';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $form): Schema
    {
        return $form
            ->components([
                Tabs::make('Product Information')
                    ->tabs([
                        Tab::make('General')
                            ->schema([
                                Section::make('Basic Information')
                                    ->compact()
                                    ->schema([
                                        TextInput::make('code')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),

                                        TextInput::make('barcode')
                                            ->unique(ignoreRecord: true)
                                            ->maxLength(255),

                                        TextInput::make('manufacturers_code')
                                            ->label('Manufacturer\'s Code')
                                            ->maxLength(255),

                                        TextInput::make('suppliers_code')
                                            ->label('Supplier\'s Code')
                                            ->maxLength(255),

                                        TextInput::make('net_weight')
                                            ->numeric()
                                            ->suffix('kg'),

                                        TextInput::make('gross_weight')
                                            ->numeric()
                                            ->suffix('kg'),
                                    ])
                                    ->columns(2),

                                Section::make('Product Details')
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('short_description')
                                            ->maxLength(500),
                                        // Category is tenant-scoped; configured in Tenant Settings section.

                                        RichEditor::make('description')
                                            ->columnSpanFull(),
                                    ]),

                                Section::make('Timestamps')
                                    ->schema([
                                        Placeholder::make('created_at')
                                            ->label('Created Date')
                                            ->content(fn (?Product $record): string => $record?->created_at?->diffForHumans() ?? '-'),

                                        Placeholder::make('updated_at')
                                            ->label('Last Modified Date')
                                            ->content(fn (?Product $record): string => $record?->updated_at?->diffForHumans() ?? '-'),
                                    ])
                                    ->columns(2)
                                    ->hidden(fn (?Product $record) => $record === null),

                                Section::make(__('eclipse-catalogue::product.sections.additional'))
                                    ->schema([
                                        Select::make('origin_country_id')
                                            ->label(__('eclipse-catalogue::product.fields.origin_country_id'))
                                            ->relationship('originCountry', 'name')
                                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->id} - {$record->name}")
                                            ->searchable(['id', 'name'])
                                            ->preload()
                                            ->placeholder(__('eclipse-catalogue::product.placeholders.origin_country_id')),

                                        Select::make('tariff_code_id')
                                            ->label(__('eclipse-catalogue::product.fields.tariff_code_id'))
                                            ->relationship('tariffCode', 'code', function ($query) {
                                                return $query->whereRaw('LENGTH(code) = 8');
                                            })
                                            ->getOptionLabelFromRecordUsing(function (TariffCode $record) {
                                                $name = $record->name;
                                                if (is_array($name)) {
                                                    $locale = app()->getLocale();
                                                    $name = $name[$locale] ?? reset($name);
                                                }

                                                return $record->code.' — '.$name;
                                            })
                                            ->searchable(['code', 'name'])
                                            ->preload()
                                            ->placeholder(__('eclipse-catalogue::product.placeholders.tariff_code_id')),
                                    ])
                                    ->collapsible()
                                    ->persistCollapsed(),

                                Section::make(__('eclipse-catalogue::product.sections.seo'))
                                    ->description(__('eclipse-catalogue::product.sections.seo_description'))
                                    ->schema([
                                        TextInput::make('meta_title')
                                            ->label(__('eclipse-catalogue::product.fields.meta_title'))
                                            ->maxLength(255)
                                            ->placeholder(__('eclipse-catalogue::product.placeholders.meta_title')),

                                        Textarea::make('meta_description')
                                            ->label(__('eclipse-catalogue::product.fields.meta_description'))
                                            ->rows(3)
                                            ->placeholder(__('eclipse-catalogue::product.placeholders.meta_description')),
                                    ])
                                    ->collapsible()
                                    ->persistCollapsed(),

                                GenericTenantFieldsComponent::make(
                                    tenantFlags: ['is_active', 'has_free_delivery'],
                                    mutuallyExclusiveFlagSets: [],
                                    translationPrefix: 'eclipse-catalogue::product',
                                    extraFieldsBuilder: function (int $tenantId, string $tenantName) {
                                        return [
                                            Select::make("tenant_data.{$tenantId}.category_id")
                                                ->label(__('eclipse-catalogue::product.fields.category_id'))
                                                ->options(function () use ($tenantId) {
                                                    return Category::query()
                                                        ->withoutGlobalScopes()
                                                        ->where(config('eclipse-catalogue.tenancy.foreign_key', 'site_id'), $tenantId)
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')
                                                        ->toArray();
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->placeholder(__('eclipse-catalogue::product.placeholders.category_id')),

                                            Select::make("tenant_data.{$tenantId}.product_status_id")
                                                ->label(__('eclipse-catalogue::product-status.singular'))
                                                ->options(function () use ($tenantId) {
                                                    $query = ProductStatus::query();
                                                    $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');
                                                    if ($tenantFK) {
                                                        $query->where($tenantFK, $tenantId);
                                                    }

                                                    return $query->orderBy('priority')->get()->mapWithKeys(function ($status) {
                                                        $title = is_array($status->title) ? ($status->title[app()->getLocale()] ?? reset($status->title)) : $status->title;

                                                        return [$status->id => $title];
                                                    })->toArray();
                                                })
                                                ->searchable()
                                                ->preload(),

                                            Select::make("tenant_data.{$tenantId}.groups")
                                                ->label('Groups')
                                                ->multiple()
                                                ->options(function () use ($tenantId) {
                                                    return Group::query()
                                                        ->where(config('eclipse-catalogue.tenancy.foreign_key', 'site_id'), $tenantId)
                                                        ->orderBy('name')
                                                        ->pluck('name', 'id')
                                                        ->toArray();
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->helperText('Select groups for this tenant'),
                                            TextInput::make("tenant_data.{$tenantId}.sorting_label")
                                                ->label(__('eclipse-catalogue::product.fields.sorting_label'))
                                                ->maxLength(255),

                                            DateTimePicker::make("tenant_data.{$tenantId}.available_from_date")
                                                ->label(__('eclipse-catalogue::product.fields.available_from_date')),
                                        ];
                                    },
                                    sectionTitle: __('eclipse-catalogue::product.sections.tenant_settings'),
                                    sectionDescription: __('eclipse-catalogue::product.sections.tenant_settings_description'),
                                ),
                            ]),

                        Tab::make(__('eclipse-catalogue::product.price.tab'))
                            ->badge(fn (?Product $record) => $record?->prices()->count() ?? 0)
                            ->schema([
                                View::make('eclipse-catalogue::product.prices-table')
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Properties')
                            ->schema([
                                Section::make('Product Type Selection')
                                    ->description('Select the product type to see available properties')
                                    ->schema([
                                        Select::make('product_type_id')
                                            ->label(__('eclipse-catalogue::product.fields.product_type'))
                                            ->relationship(
                                                'type',
                                                'name',
                                                function ($query) {
                                                    $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                                                    $currentTenant = Filament::getTenant();

                                                    if ($tenantFK && $currentTenant) {
                                                        return $query->whereHas('productTypeData', function ($q) use ($tenantFK, $currentTenant) {
                                                            $q->where($tenantFK, $currentTenant->id)
                                                                ->where('is_active', true);
                                                        });
                                                    }

                                                    return $query->whereHas('productTypeData', function ($q) {
                                                        $q->where('is_active', true);
                                                    });
                                                }
                                            )
                                            ->searchable()
                                            ->preload()
                                            ->placeholder(__('eclipse-catalogue::product.placeholders.product_type'))
                                            ->reactive(),
                                    ])
                                    ->columns(1),

                                Section::make('Product Properties')
                                    ->description('Select values for properties applicable to this product type')
                                    ->schema(function (Get $get, ?Product $record) {
                                        $productTypeId = $get('product_type_id') ?? $record?->product_type_id;

                                        if (! $productTypeId) {
                                            return [
                                                Placeholder::make('no_type')
                                                    ->label('')
                                                    ->content('Please select a product type first to see available properties.'),
                                            ];
                                        }

                                        $properties = Property::where('is_active', true)
                                            ->where(function ($query) use ($productTypeId) {
                                                $query->where('is_global', true)
                                                    ->orWhereHas('productTypes', function ($q) use ($productTypeId) {
                                                        $q->where('pim_product_types.id', $productTypeId);
                                                    });
                                            })
                                            ->with(['values' => function ($query) {
                                                $query->orderBy('sort');
                                            }])
                                            ->get();

                                        $schema = [];

                                        foreach ($properties as $property) {
                                            if ($property->isListType() || $property->isColorType()) {
                                                $valueOptions = $property->values->pluck('value', 'id')->toArray();

                                                if (empty($valueOptions)) {
                                                    continue;
                                                }

                                                $fieldType = $property->getFormFieldType();
                                                $fieldName = "property_values_{$property->id}";
                                                $displayName = $property->internal_name ?: (is_array($property->name)
                                                    ? ($property->name[app()->getLocale()] ?? reset($property->name))
                                                    : $property->name);

                                                switch ($fieldType) {
                                                    case 'radio':
                                                        $schema[] = Radio::make($fieldName)
                                                            ->label($displayName)
                                                            ->options($valueOptions)
                                                            ->descriptions($property->values->pluck('info_url', 'id')->filter()->toArray())
                                                            ->helperText($property->description)
                                                            ->createOptionForm([
                                                                TextInput::make('value')
                                                                    ->label('Value')
                                                                    ->required()
                                                                    ->maxLength(255),
                                                                TextInput::make('info_url')
                                                                    ->label('Info URL')
                                                                    ->url()
                                                                    ->maxLength(255),
                                                                TextInput::make('image')
                                                                    ->label('Image')
                                                                    ->maxLength(255),
                                                            ])
                                                            ->createOptionAction(function ($action) {
                                                                return $action
                                                                    ->modalHeading('Create New Property Value')
                                                                    ->modalSubmitActionLabel('Create Value');
                                                            });
                                                        break;

                                                    case 'select':
                                                        $schema[] = Select::make($fieldName)
                                                            ->label($displayName)
                                                            ->options($valueOptions)
                                                            ->searchable()
                                                            ->createOptionForm([
                                                                TextInput::make('value')
                                                                    ->label('Value')
                                                                    ->required()
                                                                    ->maxLength(255),
                                                                TextInput::make('info_url')
                                                                    ->label('Info URL')
                                                                    ->url()
                                                                    ->maxLength(255),
                                                                TextInput::make('image')
                                                                    ->label('Image')
                                                                    ->maxLength(255),
                                                            ])
                                                            ->createOptionAction(function ($action) {
                                                                return $action
                                                                    ->modalHeading('Create New Property Value')
                                                                    ->modalSubmitActionLabel('Create Value');
                                                            })
                                                            ->helperText($property->description);
                                                        break;

                                                    case 'checkbox':
                                                        $schema[] = CheckboxList::make($fieldName)
                                                            ->label($displayName)
                                                            ->options($valueOptions)
                                                            ->descriptions($property->values->pluck('info_url', 'id')->filter()->toArray())
                                                            ->helperText($property->description)
                                                            ->rules($property->max_values > 1 ? ["max:{$property->max_values}"] : [])
                                                            ->createOptionForm([
                                                                TextInput::make('value')
                                                                    ->label('Value')
                                                                    ->required()
                                                                    ->maxLength(255),
                                                                TextInput::make('info_url')
                                                                    ->label('Info URL')
                                                                    ->url()
                                                                    ->maxLength(255),
                                                                TextInput::make('image')
                                                                    ->label('Image')
                                                                    ->maxLength(255),
                                                            ])
                                                            ->createOptionAction(function ($action) {
                                                                return $action
                                                                    ->modalHeading('Create New Property Value')
                                                                    ->modalSubmitActionLabel('Create Value');
                                                            });
                                                        break;

                                                    case 'multiselect':
                                                        $schema[] = Select::make($fieldName)
                                                            ->label($displayName)
                                                            ->options($valueOptions)
                                                            ->multiple()
                                                            ->searchable()
                                                            ->createOptionForm([
                                                                TextInput::make('value')
                                                                    ->label('Value')
                                                                    ->required()
                                                                    ->maxLength(255),
                                                                TextInput::make('info_url')
                                                                    ->label('Info URL')
                                                                    ->url()
                                                                    ->maxLength(255),
                                                                TextInput::make('image')
                                                                    ->label('Image')
                                                                    ->maxLength(255),
                                                            ])
                                                            ->createOptionAction(function ($action) {
                                                                return $action
                                                                    ->modalHeading('Create New Property Value')
                                                                    ->modalSubmitActionLabel('Create Value');
                                                            })
                                                            ->helperText($property->description)
                                                            ->rules($property->max_values > 1 ? ["max:{$property->max_values}"] : []);
                                                        break;
                                                }
                                            }
                                        }

                                        foreach ($properties as $property) {
                                            if ($property->isCustomType()) {
                                                $fieldName = "custom_property_{$property->id}";
                                                $displayName = $property->internal_name ?: (is_array($property->name)
                                                    ? ($property->name[app()->getLocale()] ?? reset($property->name))
                                                    : $property->name);

                                                switch ($property->input_type) {
                                                    case 'string':
                                                        if ($property->supportsMultilang()) {
                                                            $schema[] = InlineTranslatableField::make($fieldName)
                                                                ->label($displayName)
                                                                ->type('string')
                                                                ->maxLength(255)
                                                                ->helperText($property->description)
                                                                ->rules(['string', 'max:255'])
                                                                ->getComponent();
                                                        } else {
                                                            $schema[] = TextInput::make($fieldName)
                                                                ->label($displayName)
                                                                ->maxLength(255)
                                                                ->helperText($property->description)
                                                                ->rules(['string', 'max:255']);
                                                        }
                                                        break;

                                                    case 'text':
                                                        if ($property->supportsMultilang()) {
                                                            $schema[] = InlineTranslatableField::make($fieldName)
                                                                ->label($displayName)
                                                                ->type('text')
                                                                ->maxLength(65535)
                                                                ->helperText($property->description)
                                                                ->rules(['string', 'max:65535'])
                                                                ->getComponent();
                                                        } else {
                                                            $schema[] = RichEditor::make($fieldName)
                                                                ->label($displayName)
                                                                ->helperText($property->description)
                                                                ->rules(['string', 'max:65535'])
                                                                ->columnSpanFull();
                                                        }
                                                        break;

                                                    case 'integer':
                                                        $schema[] = TextInput::make($fieldName)
                                                            ->label($displayName)
                                                            ->numeric()
                                                            ->helperText($property->description)
                                                            ->rules(['integer']);
                                                        break;

                                                    case 'decimal':
                                                        $schema[] = TextInput::make($fieldName)
                                                            ->label($displayName)
                                                            ->numeric()
                                                            ->step(0.01)
                                                            ->helperText($property->description)
                                                            ->rules(['numeric']);
                                                        break;

                                                    case 'date':
                                                        $schema[] = DatePicker::make($fieldName)
                                                            ->label($displayName)
                                                            ->helperText($property->description)
                                                            ->rules(['date']);
                                                        break;

                                                    case 'datetime':
                                                        $schema[] = DateTimePicker::make($fieldName)
                                                            ->label($displayName)
                                                            ->helperText($property->description)
                                                            ->rules(['date']);
                                                        break;

                                                    case 'file':
                                                        if ($property->supportsMultilang()) {
                                                            $schema[] = InlineTranslatableField::make($fieldName)
                                                                ->label($displayName)
                                                                ->type('file')
                                                                ->multiple($property->max_values > 1)
                                                                ->maxFiles($property->max_values)
                                                                ->helperText($property->description)
                                                                ->rules($property->max_values > 1 ? ['array', "max:{$property->max_values}"] : ['file'])
                                                                ->getComponent();
                                                        } else {
                                                            if ($property->max_values > 1) {
                                                                $schema[] = FileUpload::make($fieldName)
                                                                    ->label($displayName)
                                                                    ->multiple()
                                                                    ->helperText($property->description)
                                                                    ->rules(['array', "max:{$property->max_values}"]);
                                                            } else {
                                                                $schema[] = FileUpload::make($fieldName)
                                                                    ->label($displayName)
                                                                    ->helperText($property->description)
                                                                    ->rules(['file']);
                                                            }
                                                        }
                                                        break;
                                                }
                                            }
                                        }

                                        return $schema ?: [
                                            Placeholder::make('no_properties')
                                                ->label('')
                                                ->content('No properties are configured for this product type.'),
                                        ];
                                    })
                                    ->reactive()
                                    ->columns(2),
                            ]),

                        Tab::make('Images')
                            ->schema([
                                ImageManager::make('images')
                                    ->label('')
                                    ->collection('images')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id'),

                ImageColumn::make('cover_image')
                    ->stacked()
                    ->label('Image')
                    ->getStateUsing(function (Product $record) {
                        $url = $record->getFirstMediaUrl('images', 'thumb');

                        return $url ?: null;
                    })
                    ->circular()
                    ->defaultImageUrl(static::getPlaceholderImageUrl())
                    ->extraImgAttributes(function (Product $record) {
                        $coverMedia = $record->getMedia('images')
                            ->filter(fn ($media) => $media->getCustomProperty('is_cover', false))
                            ->first();

                        if (! $coverMedia) {
                            $coverMedia = $record->getMedia('images')->first();
                        }

                        $fullImageUrl = $coverMedia ? $coverMedia->getUrl() : null;
                        $imageName = $coverMedia ? json_encode($coverMedia->getCustomProperty('name', [])) : '{}';
                        $imageDescription = $coverMedia ? json_encode($coverMedia->getCustomProperty('description', [])) : '{}';

                        return [
                            'class' => 'cursor-pointer product-image-trigger',
                            'data-url' => $fullImageUrl ?: static::getPlaceholderImageUrl(),
                            'data-image-name' => htmlspecialchars($imageName, ENT_QUOTES, 'UTF-8'),
                            'data-image-description' => htmlspecialchars($imageDescription, ENT_QUOTES, 'UTF-8'),
                            'data-product-name' => htmlspecialchars(json_encode($record->getTranslations('name')), ENT_QUOTES, 'UTF-8'),
                            'data-product-code' => $record->code ?: '',
                            'data-filename' => $coverMedia ? $coverMedia->file_name : '',
                            'onclick' => 'event.stopPropagation(); return false;',
                        ];
                    }),

                TextColumn::make('name')
                    ->toggleable(false),

                TextColumn::make('status')
                    ->label(__('eclipse-catalogue::product-status.singular'))
                    ->badge()
                    ->getStateUsing(function (Product $record) {
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                        $currentTenant = Filament::getTenant();

                        $status = null;

                        if ($record->relationLoaded('productData')) {
                            $row = $record->productData
                                ->when($tenantFK && $currentTenant, fn ($c) => $c->where($tenantFK, $currentTenant->id))
                                ->first();
                            if ($row && $row->relationLoaded('status')) {
                                $status = $row->status;
                            }
                        }

                        if (! $status) {
                            return __('eclipse-catalogue::product-status.fields.no_status') ?? 'No status';
                        }

                        return is_array($status->title) ? ($status->title[app()->getLocale()] ?? reset($status->title)) : $status->title;
                    })
                    ->color(function (Product $record) {
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                        $currentTenant = Filament::getTenant();

                        $status = null;
                        if ($record->relationLoaded('productData')) {
                            $row = $record->productData
                                ->when($tenantFK && $currentTenant, fn ($c) => $c->where($tenantFK, $currentTenant->id))
                                ->first();
                            if ($row && $row->relationLoaded('status')) {
                                $status = $row->status;
                            }
                        }

                        return $status?->label_type ?? 'gray';
                    })
                    ->extraAttributes(function (Product $record) {
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                        $currentTenant = Filament::getTenant();

                        $status = null;
                        if ($record->relationLoaded('productData')) {
                            $row = $record->productData
                                ->when($tenantFK && $currentTenant, fn ($c) => $c->where($tenantFK, $currentTenant->id))
                                ->first();
                            if ($row && $row->relationLoaded('status')) {
                                $status = $row->status;
                            }
                        }

                        return $status ? ['class' => LabelType::badgeClass($status->label_type)] : [];
                    })
                    ->searchable(false)
                    ->sortable(false),

                TextColumn::make('category')
                    ->label('Category')
                    ->getStateUsing(function (Product $record) {
                        $category = $record->currentTenantData()?->category;
                        if (! $category) {
                            return null;
                        }

                        return is_array($category->name) ? ($category->name[app()->getLocale()] ?? reset($category->name)) : $category->name;
                    }),

                TextColumn::make('type.name')
                    ->label(__('eclipse-catalogue::product.table.columns.type')),

                TextColumn::make('groups.name')
                    ->label('Groups')
                    ->badge()
                    ->separator(',')
                    ->limit(3)
                    ->toggleable()
                    ->getStateUsing(function (Product $record) {
                        $currentTenant = Filament::getTenant();
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');

                        if ($currentTenant) {
                            return $record->groups()
                                ->where($tenantFK, $currentTenant->id)
                                ->pluck('name')
                                ->toArray();
                        }

                        return $record->groups->pluck('name')->toArray();
                    }),

                IconColumn::make('is_active')
                    ->label(__('eclipse-catalogue::product.table.columns.is_active'))
                    ->boolean(),

                TextColumn::make('originCountry.name')
                    ->label(__('eclipse-catalogue::product.fields.origin_country_id')),

                TextColumn::make('tariffCode.code')
                    ->label(__('eclipse-catalogue::product.fields.tariff_code_id'))
                    ->getStateUsing(function (Product $record) {
                        $tariffCode = $record->tariffCode;
                        if (! $tariffCode) {
                            return null;
                        }

                        $name = $tariffCode->name;
                        if (is_array($name)) {
                            $locale = app()->getLocale();
                            $name = $name[$locale] ?? reset($name);
                        }

                        return $tariffCode->code.' — '.$name;
                    })
                    ->toggleable()
                    ->toggledHiddenByDefault()
                    ->searchable()
                    ->copyable(),

                TextColumn::make('short_description')
                    ->words(5),

                TextColumn::make('code')
                    ->copyable(),

                TextColumn::make('barcode'),

                TextColumn::make('manufacturers_code'),

                TextColumn::make('suppliers_code'),

                TextColumn::make('net_weight')
                    ->numeric(3)
                    ->suffix(' kg'),

                TextColumn::make('gross_weight')
                    ->numeric(3)
                    ->suffix(' kg'),

                ...static::getCustomPropertyColumns(),
            ])
            ->searchable()
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('product_status_id')
                    ->label(__('eclipse-catalogue::product-status.singular'))
                    ->multiple()
                    ->options(function () {
                        $query = ProductStatus::query();
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                        $currentTenant = Filament::getTenant();
                        if ($tenantFK && $currentTenant) {
                            $query->where($tenantFK, $currentTenant->id);
                        }

                        return $query->orderBy('priority')->get()->mapWithKeys(function ($status) {
                            $title = is_array($status->title) ? ($status->title[app()->getLocale()] ?? reset($status->title)) : $status->title;

                            return [$status->id => $title];
                        })->toArray();
                    })
                    ->query(function (Builder $query, array $data) {
                        $selected = $data['values'] ?? ($data['value'] ?? null);
                        if (empty($selected)) {
                            return;
                        }
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                        $currentTenant = Filament::getTenant();
                        $query->whereHas('productData', function ($q) use ($selected, $tenantFK, $currentTenant) {
                            if ($tenantFK && $currentTenant) {
                                $q->where($tenantFK, $currentTenant->id);
                            }
                            $q->whereIn('product_status_id', (array) $selected);
                        });
                    }),
                SelectFilter::make('category_id')
                    ->label('Categories')
                    ->multiple()
                    ->options(Category::getHierarchicalOptions())
                    ->query(function (Builder $query, array $data) {
                        $selected = $data['values'] ?? ($data['value'] ?? null);
                        if (empty($selected)) {
                            return;
                        }
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                        $currentTenant = Filament::getTenant();
                        $query->whereHas('productData', function ($q) use ($selected, $tenantFK, $currentTenant) {
                            if ($tenantFK && $currentTenant) {
                                $q->where($tenantFK, $currentTenant->id);
                            }
                            $q->whereIn('category_id', (array) $selected);
                        });
                    }),
                SelectFilter::make('product_type_id')
                    ->label(__('eclipse-catalogue::product.filters.product_type'))
                    ->multiple()
                    ->options(function () {
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                        $currentTenant = Filament::getTenant();

                        $query = ProductType::query();

                        if ($tenantFK && $currentTenant) {
                            $query->whereHas('productTypeData', function ($q) use ($tenantFK, $currentTenant) {
                                $q->where($tenantFK, $currentTenant->id)
                                    ->where('is_active', true);
                            });
                        } else {
                            $query->whereHas('productTypeData', function ($q) {
                                $q->where('is_active', true);
                            });
                        }

                        return $query->pluck('name', 'id')->toArray();
                    }),
                SelectFilter::make('origin_country_id')
                    ->label(__('eclipse-catalogue::product.fields.origin_country_id'))
                    ->multiple()
                    ->options(fn () => Country::query()->orderBy('name')->pluck('name', 'id')->toArray()),

                SelectFilter::make('tariff_code_id')
                    ->label(__('eclipse-catalogue::product.fields.tariff_code_id'))
                    ->multiple()
                    ->options(function () {
                        return TariffCode::query()
                            ->whereRaw('LENGTH(code) = 8')
                            ->orderBy('code')
                            ->get()
                            ->mapWithKeys(function ($tariffCode) {
                                $name = $tariffCode->name;
                                if (is_array($name)) {
                                    $locale = app()->getLocale();
                                    $name = $name[$locale] ?? reset($name);
                                }

                                return [$tariffCode->id => $tariffCode->code.' — '.$name];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('groups')
                    ->label('Groups')
                    ->multiple()
                    ->relationship('groups', 'name', function ($query) {
                        $currentTenant = Filament::getTenant();
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');
                        if ($currentTenant) {
                            return $query->where($tenantFK, $currentTenant->id);
                        }

                        return $query;
                    }),
                TernaryFilter::make('is_active')
                    ->label(__('eclipse-catalogue::product.table.columns.is_active'))
                    ->queries(
                        true: function (Builder $query) {
                            $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                            $currentTenant = Filament::getTenant();

                            return $query->whereHas('productData', function ($q) use ($tenantFK, $currentTenant) {
                                $q->where('is_active', true);
                                if ($tenantFK && $currentTenant) {
                                    $q->where($tenantFK, $currentTenant->id);
                                }
                            });
                        },
                        false: function (Builder $query) {
                            $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                            $currentTenant = Filament::getTenant();

                            return $query->whereHas('productData', function ($q) use ($tenantFK, $currentTenant) {
                                $q->where('is_active', false);
                                if ($tenantFK && $currentTenant) {
                                    $q->where($tenantFK, $currentTenant->id);
                                }
                            });
                        },
                    ),

                QueryBuilder::make()
                    ->label('Custom Properties')
                    ->constraints([
                        ...static::getCustomPropertyConstraints(),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ])
                    ->hiddenLabel()
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('sm')
                    ->color('gray')
                    ->button(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkUpdateProductsAction::make(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);

        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
        $currentTenant = Filament::getTenant();

        if ($tenantFK && $currentTenant) {
            $query->with(['productData' => function ($q) use ($tenantFK, $currentTenant) {
                $q->where($tenantFK, $currentTenant->id)->with('status');
            }]);
        } else {
            $query->with(['productData.status']);
        }

        return $query;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [
            'code',
            'barcode',
            'manufacturers_code',
            'suppliers_code',
            'name',
            'short_description',
            'description',
            'tariffCode.code',
            'tariffCode.name',
        ];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [
            'Code' => $record->code,
        ];

        if ($record->tariffCode) {
            $details['Tariff Code'] = $record->tariffCode->code;
        }

        return array_filter($details);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['customPropertyValues.property']);
    }

    protected static function getPlaceholderImageUrl(): string
    {
        $svg = view('eclipse-catalogue::components.placeholder-image')->render();

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    protected static function getCustomPropertyColumns(): array
    {
        $customProperties = Property::where('type', 'custom')->get();
        $columns = [];

        foreach ($customProperties as $property) {
            $propertyName = $property->internal_name ?: (is_array($property->name)
                ? ($property->name[app()->getLocale()] ?? reset($property->name))
                : $property->name);

            $columns[] = TextColumn::make("custom_property_{$property->id}")
                ->label($propertyName)
                ->getStateUsing(function (Product $record) use ($property) {
                    $customValue = $record->customPropertyValues()->where('property_id', $property->id)->first();

                    if (! $customValue) {
                        return null;
                    }

                    $value = $customValue->getFormattedValue();

                    if ($property->input_type === PropertyInputType::TEXT->value) {
                        $value = strip_tags($value);
                    }

                    return $value;
                })
                ->limit(30)
                ->toggleable(isToggledHiddenByDefault: true);
        }

        return $columns;
    }

    protected static function getCustomPropertyConstraints(): array
    {
        $constraints = [];
        $customProperties = Property::where('is_active', true)
            ->where('type', 'custom')
            ->where('input_type', '!=', 'file')
            ->get();

        foreach ($customProperties as $property) {
            $constraints[] = CustomPropertyConstraint::forProperty($property);
        }

        return $constraints;
    }
}
