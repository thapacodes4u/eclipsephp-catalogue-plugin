<?php

namespace Eclipse\Catalogue\Traits;

use Eclipse\Catalogue\Forms\Components\GenericTenantFieldsComponent;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;

trait HasPriceListForm
{
    /**
     * Build the base form schema for price list pages.
     */
    protected function buildPriceListFormSchema(): array
    {
        $baseSchema = [
            Section::make(__('eclipse-catalogue::price-list.sections.information'))
                ->description(__('eclipse-catalogue::price-list.sections.information_description'))
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('name')
                                ->label(__('eclipse-catalogue::price-list.fields.name'))
                                ->required()
                                ->maxLength(255)
                                ->placeholder(__('eclipse-catalogue::price-list.placeholders.name')),

                            TextInput::make('code')
                                ->label(__('eclipse-catalogue::price-list.fields.code'))
                                ->maxLength(255)
                                ->placeholder(__('eclipse-catalogue::price-list.placeholders.code'))
                                ->unique(
                                    table: 'pim_price_lists',
                                    column: 'code',
                                    ignoreRecord: $this->record ? true : false
                                ),
                        ]),

                    Grid::make(2)
                        ->schema([
                            Select::make('currency_id')
                                ->label(__('eclipse-catalogue::price-list.fields.currency'))
                                ->relationship('currency', 'name', function ($query) {
                                    return $query->select('id', 'name')->where('is_active', true);
                                })
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->id} - {$record->name}")
                                ->searchable(['id', 'name'])
                                ->preload()
                                ->required()
                                ->placeholder(__('eclipse-catalogue::price-list.placeholders.currency')),

                            Toggle::make('tax_included')
                                ->label(__('eclipse-catalogue::price-list.fields.tax_included'))
                                ->helperText(__('eclipse-catalogue::price-list.help_text.tax_included'))
                                ->default(false)
                                ->inline(false),
                        ]),

                    Textarea::make('notes')
                        ->label(__('eclipse-catalogue::price-list.fields.notes'))
                        ->placeholder(__('eclipse-catalogue::price-list.placeholders.notes'))
                        ->rows(3)
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->persistCollapsed(),
        ];

        // Add tenant fields if tenancy is enabled
        if ($this->isTenancyEnabled()) {
            $baseSchema[] = GenericTenantFieldsComponent::make(
                tenantFlags: ['is_active', 'is_default', 'is_default_purchase'],
                mutuallyExclusiveFlagSets: [['is_default', 'is_default_purchase']],
                translationPrefix: 'eclipse-catalogue::price-list',
                extraFieldsBuilder: null,
                sectionTitle: __('eclipse-catalogue::price-list.sections.tenant_settings'),
                sectionDescription: __('eclipse-catalogue::price-list.sections.tenant_settings_description'),
            );
        } else {
            // No tenancy - add simple settings section
            $baseSchema[] = Section::make(__('eclipse-catalogue::price-list.sections.settings'))
                ->description(__('eclipse-catalogue::price-list.sections.settings_description'))
                ->schema([
                    Toggle::make('is_active')
                        ->label(__('eclipse-catalogue::price-list.fields.is_active'))
                        ->helperText(__('eclipse-catalogue::price-list.help_text.is_active'))
                        ->default(true)
                        ->inline(false),

                    Fieldset::make(__('eclipse-catalogue::price-list.sections.default_settings'))
                        ->schema([
                            Toggle::make('is_default')
                                ->label(__('eclipse-catalogue::price-list.fields.is_default'))
                                ->helperText(__('eclipse-catalogue::price-list.help_text.is_default'))
                                ->inline(false)
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state && $get('is_default_purchase')) {
                                        $set('is_default_purchase', false);
                                        Notification::make()
                                            ->warning()
                                            ->title(__('eclipse-catalogue::price-list.notifications.conflict_resolved_title'))
                                            ->body(__('eclipse-catalogue::price-list.notifications.conflict_resolved_purchase_disabled_simple'))
                                            ->send();
                                    }
                                }),

                            Toggle::make('is_default_purchase')
                                ->label(__('eclipse-catalogue::price-list.fields.is_default_purchase'))
                                ->helperText(__('eclipse-catalogue::price-list.help_text.is_default_purchase'))
                                ->inline(false)
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state && $get('is_default')) {
                                        $set('is_default', false);
                                        Notification::make()
                                            ->warning()
                                            ->title(__('eclipse-catalogue::price-list.notifications.conflict_resolved_title'))
                                            ->body(__('eclipse-catalogue::price-list.notifications.conflict_resolved_selling_disabled_simple'))
                                            ->send();
                                    }
                                }),
                        ])
                        ->columns(1),
                ])
                ->collapsible()
                ->persistCollapsed();
        }

        return $baseSchema;
    }
}
