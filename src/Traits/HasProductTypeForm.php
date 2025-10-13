<?php

namespace Eclipse\Catalogue\Traits;

use Eclipse\Catalogue\Forms\Components\GenericTenantFieldsComponent;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

trait HasProductTypeForm
{
    /**
     * Build the base form schema for product type pages.
     */
    protected function buildProductTypeFormSchema(): array
    {
        $baseSchema = [
            Section::make(__('eclipse-catalogue::product-type.sections.information'))
                ->description(__('eclipse-catalogue::product-type.sections.information_description'))
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label(__('eclipse-catalogue::product-type.fields.name'))
                                ->required()
                                ->maxLength(255)
                                ->placeholder(__('eclipse-catalogue::product-type.placeholders.name')),

                            TextInput::make('code')
                                ->label(__('eclipse-catalogue::product-type.fields.code'))
                                ->maxLength(255)
                                ->placeholder(__('eclipse-catalogue::product-type.placeholders.code'))
                                ->unique(
                                    table: 'pim_product_types',
                                    column: 'code',
                                    ignoreRecord: $this->record ? true : false
                                )
                                ->columnSpanFull(),
                        ]),
                ])
                ->collapsible()
                ->persistCollapsed(),
        ];

        // Add tenant fields if tenancy is enabled
        if ($this->isTenancyEnabled()) {
            $baseSchema[] = GenericTenantFieldsComponent::make(
                tenantFlags: ['is_active', 'is_default'],
                mutuallyExclusiveFlagSets: [],
                translationPrefix: 'eclipse-catalogue::product-type',
                extraFieldsBuilder: null,
                sectionTitle: __('eclipse-catalogue::product-type.sections.tenant_settings'),
                sectionDescription: __('eclipse-catalogue::product-type.sections.tenant_settings_description')
            );
        } else {
            // No tenancy - add simple settings section
            $baseSchema[] = Section::make(__('eclipse-catalogue::product-type.sections.settings'))
                ->description(__('eclipse-catalogue::product-type.sections.settings_description'))
                ->schema([
                    Toggle::make('is_active')
                        ->label(__('eclipse-catalogue::product-type.fields.is_active'))
                        ->helperText(__('eclipse-catalogue::product-type.help_text.is_active'))
                        ->default(true)
                        ->inline(false),

                    Toggle::make('is_default')
                        ->label(__('eclipse-catalogue::product-type.fields.is_default'))
                        ->helperText(__('eclipse-catalogue::product-type.help_text.is_default'))
                        ->inline(false),
                ])
                ->collapsible()
                ->persistCollapsed();
        }

        return $baseSchema;
    }
}
