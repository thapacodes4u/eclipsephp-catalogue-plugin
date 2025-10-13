<?php

namespace Eclipse\Catalogue;

use Eclipse\Catalogue\Filament\Resources\CategoryResource;
use Eclipse\Catalogue\Filament\Resources\GroupResource;
use Eclipse\Catalogue\Filament\Resources\MeasureUnitResource;
use Eclipse\Catalogue\Filament\Resources\PriceListResource;
use Eclipse\Catalogue\Filament\Resources\ProductResource;
use Eclipse\Catalogue\Filament\Resources\ProductStatusResource;
use Eclipse\Catalogue\Filament\Resources\ProductTypeResource;
use Eclipse\Catalogue\Filament\Resources\PropertyResource;
use Eclipse\Catalogue\Filament\Resources\PropertyValueResource;
use Eclipse\Catalogue\Filament\Resources\TaxClassResource;
use Eclipse\Catalogue\Livewire\TenantSwitcher;
use Eclipse\Catalogue\Models\Category;
use Eclipse\Catalogue\Models\Product;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CatalogueServiceProvider extends PackageServiceProvider
{
    public static string $name = 'eclipse-catalogue';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasViews()
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->discoversMigrations()
            ->runsMigrations()
            ->hasAssets();
    }

    public function register()
    {
        parent::register();

        $settings = Config::get('scout.typesense.model-settings', []);

        $settings += [
            Product::class => Product::getTypesenseSettings(),
            Category::class => Category::getTypesenseSettings(),
        ];

        Config::set('scout.typesense.model-settings', $settings);
    }

    public function boot()
    {
        parent::boot();

        // Merge per-resource abilities into the effective config
        $this->app->booted(function () {
            $manage = config('filament-shield.resources.manage', []);

            $pluginManage = [
                ProductResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'restore',
                    'restoreAny',
                    'delete',
                    'deleteAny',
                    'forceDelete',
                    'forceDeleteAny',
                ],
                CategoryResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'restore',
                    'restoreAny',
                    'delete',
                    'deleteAny',
                    'forceDelete',
                    'forceDeleteAny',
                ],
                ProductTypeResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'restore',
                    'restoreAny',
                    'delete',
                    'deleteAny',
                    'forceDelete',
                    'forceDeleteAny',
                ],
                PropertyResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'delete',
                    'deleteAny',
                    'forceDelete',
                    'forceDeleteAny',
                    'restore',
                    'restoreAny',
                ],
                PropertyValueResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'delete',
                    'deleteAny',
                ],
                GroupResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'delete',
                    'deleteAny',
                ],
                PriceListResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'restore',
                    'restoreAny',
                    'delete',
                    'deleteAny',
                    'forceDelete',
                    'forceDeleteAny',
                ],
                MeasureUnitResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'restore',
                    'restoreAny',
                    'delete',
                    'deleteAny',
                    'forceDelete',
                    'forceDeleteAny',
                ],
                TaxClassResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'restore',
                    'restoreAny',
                    'delete',
                    'deleteAny',
                    'forceDelete',
                    'forceDeleteAny',
                ],
                ProductStatusResource::class => [
                    'viewAny',
                    'view',
                    'create',
                    'update',
                    'delete',
                    'deleteAny',
                ],
            ];

            $manage = array_replace_recursive($manage, $pluginManage);
            config()->set('filament-shield.resources.manage', $manage);
        });

        // Register Livewire components
        if (class_exists(Livewire::class)) {
            Livewire::component('eclipse-catalogue::tenant-switcher', TenantSwitcher::class);
        }

        FilamentAsset::register([
            Css::make('eclipse-catalogue', __DIR__.'/../resources/css/catalogue.css'),
        ], package: static::$name);
    }
}
