<?php

namespace Eclipse\Catalogue\Filament\Tables\Actions;

use Eclipse\Catalogue\Models\Category;
use Eclipse\Catalogue\Models\Group;
use Eclipse\Catalogue\Models\PriceList;
use Eclipse\Catalogue\Models\ProductStatus;
use Eclipse\Catalogue\Models\ProductType;
use Eclipse\Catalogue\Services\ProductBulkUpdater;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\App;

class BulkUpdateProductsAction extends BulkAction
{
    /**
     * Make the bulk update action.
     *
     * @param  string|null  $name  The name of the action.
     * @return static The bulk update action.
     */
    public static function make(?string $name = null): static
    {
        $action = parent::make($name ?? 'bulk_update')
            ->label('Edit')
            ->icon('heroicon-o-wrench')
            ->modalHeading('Bulk edit')
            ->deselectRecordsAfterCompletion()
            ->form([
                Select::make('product_status_id')
                    ->label(__('eclipse-catalogue::product-status.singular'))
                    ->options(function () {
                        $query = ProductStatus::query();
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                        $currentTenant = Filament::getTenant();

                        if ($tenantFK && $currentTenant) {
                            $query->where($tenantFK, $currentTenant->id);
                        }

                        $noChange = __('eclipse-catalogue::common.no_change') ?: '-- no change --';
                        $options = $query->orderBy('priority')->get()->mapWithKeys(function ($status) {
                            $title = is_array($status->title)
                                ? ($status->title[app()->getLocale()] ?? reset($status->title))
                                : $status->title;

                            return [$status->id => $title];
                        })->toArray();

                        return ['__no_change__' => $noChange] + $options;
                    })
                    ->searchable()
                    ->default('__no_change__'),
                Select::make('product_type_id')
                    ->label('Product Type')
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

                        $noChange = __('eclipse-catalogue::common.no_change') ?: '-- no change --';
                        $options = $query->pluck('name', 'id')->toArray();

                        return ['__no_change__' => $noChange] + $options;
                    })
                    ->searchable()
                    ->default('__no_change__'),
                Select::make('free_delivery')
                    ->label(__('eclipse-catalogue::product.fields.has_free_delivery'))
                    ->options(function () {
                        $noChange = __('eclipse-catalogue::common.no_change') ?: '-- no change --';
                        $yes = __('eclipse-catalogue::common.yes') ?: 'Yes';
                        $no = __('eclipse-catalogue::common.no') ?: 'No';

                        return [
                            '__no_change__' => $noChange,
                            '1' => $yes,
                            '0' => $no,
                        ];
                    })
                    ->selectablePlaceholder(false)
                    ->default('__no_change__'),
                Select::make('category_id')
                    ->label('Category')
                    ->options(function () {
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');
                        $currentTenant = Filament::getTenant();
                        $query = Category::query()->withoutGlobalScopes();
                        if ($tenantFK && $currentTenant) {
                            $query->where($tenantFK, $currentTenant->id);
                        }

                        $noChange = __('eclipse-catalogue::common.no_change') ?: '-- no change --';
                        $options = $query->orderBy('name')->pluck('name', 'id')->toArray();

                        return ['__no_change__' => $noChange] + $options;
                    })
                    ->searchable()
                    ->default('__no_change__'),
                Section::make('Groups')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('groups_add_ids')
                                    ->label('Add to groups')
                                    ->multiple()
                                    ->options(fn () => Group::query()->forCurrentTenant()->pluck('name', 'id')->toArray())
                                    ->searchable(),
                                Select::make('groups_remove_ids')
                                    ->label('Remove from groups')
                                    ->multiple()
                                    ->options(fn () => Group::query()->forCurrentTenant()->pluck('name', 'id')->toArray())
                                    ->searchable(),
                            ]),
                    ]),
                Section::make('Prices')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Select::make('price_list_id')
                            ->label(__('eclipse-catalogue::product.price.fields.price_list'))
                            ->options(function () {
                                $tenantFK = config('eclipse-catalogue.tenancy.foreign_key');
                                $currentTenant = Filament::getTenant();

                                $query = PriceList::query();
                                if ($tenantFK && $currentTenant) {
                                    $query->whereHas('priceListData', function ($q) use ($tenantFK, $currentTenant) {
                                        $q->where($tenantFK, $currentTenant->id)
                                            ->where('is_active', true);
                                    });
                                } else {
                                    $query->whereHas('priceListData', function ($q) {
                                        $q->where('is_active', true);
                                    });
                                }

                                return $query->orderBy('name')->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (! $state) {
                                    return;
                                }
                                $pl = PriceList::query()->select('id', 'tax_included')->find($state);
                                if ($pl) {
                                    $set('tax_included', (bool) $pl->tax_included);
                                }
                            })
                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                \Filament\Schemas\Components\Group::make()
                                    ->schema([
                                        TextInput::make('price')
                                            ->label(__('eclipse-catalogue::product.price.fields.price'))
                                            ->numeric()
                                            ->rule('decimal:0,5'),
                                        Checkbox::make('tax_included')
                                            ->label(__('eclipse-catalogue::product.price.fields.tax_included'))
                                            ->inline(false)
                                            ->default(false),
                                    ]),
                                \Filament\Schemas\Components\Group::make()
                                    ->schema([
                                        DatePicker::make('valid_from')
                                            ->label(__('eclipse-catalogue::product.price.fields.valid_from'))
                                            ->native(false)
                                            ->default(fn () => now()),
                                        DatePicker::make('valid_to')
                                            ->label(__('eclipse-catalogue::product.price.fields.valid_to'))
                                            ->native(false)
                                            ->nullable(),
                                    ]),
                            ]),
                    ]),
                Section::make('Images')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                \Filament\Schemas\Components\Group::make()
                                    ->schema([
                                        FileUpload::make('cover_image')
                                            ->label('Cover image')
                                            ->image()
                                            ->imageEditor(false)
                                            ->directory('temp-images')
                                            ->visibility('public')
                                            ->storeFiles(true)
                                            ->preserveFilenames()
                                            ->nullable(),
                                        FileUpload::make('image_2')
                                            ->label('Image #2')
                                            ->image()
                                            ->imageEditor(false)
                                            ->directory('temp-images')
                                            ->visibility('public')
                                            ->storeFiles(true)
                                            ->preserveFilenames()
                                            ->nullable(),
                                    ]),
                                \Filament\Schemas\Components\Group::make()
                                    ->schema([
                                        FileUpload::make('image_1')
                                            ->label('Image #1')
                                            ->image()
                                            ->imageEditor(false)
                                            ->directory('temp-images')
                                            ->visibility('public')
                                            ->storeFiles(true)
                                            ->preserveFilenames()
                                            ->nullable(),
                                        FileUpload::make('image_3')
                                            ->label('Image #3')
                                            ->image()
                                            ->imageEditor(false)
                                            ->directory('temp-images')
                                            ->visibility('public')
                                            ->storeFiles(true)
                                            ->preserveFilenames()
                                            ->nullable(),
                                    ]),
                            ]),
                    ]),
            ])
            ->action(function (array $data, $records) {
                /** @var ProductBulkUpdater $updater */
                $updater = App::make(ProductBulkUpdater::class);
                $result = $updater->apply($data, $records);

                $notification = Notification::make();
                if (($result['successCount'] ?? 0) > 0) {
                    $title = $result['successCount'] === 1
                        ? 'Updated 1 product'
                        : "Updated {$result['successCount']} products";
                    $notification->title($title)->success();
                } else {
                    $notification->title('No changes applied')->warning();
                }

                if (! empty($result['errors'] ?? [])) {
                    $notification->body('Some updates failed: '.implode(' | ', array_unique($result['errors'])));
                }

                $notification->send();
            });

        return $action;
    }
}
