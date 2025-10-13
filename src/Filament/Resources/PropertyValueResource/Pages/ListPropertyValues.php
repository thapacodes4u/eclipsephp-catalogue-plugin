<?php

namespace Eclipse\Catalogue\Filament\Resources\PropertyValueResource\Pages;

use Eclipse\Catalogue\Enums\PropertyType;
use Eclipse\Catalogue\Filament\Resources\PropertyResource;
use Eclipse\Catalogue\Filament\Resources\PropertyValueResource;
use Eclipse\Catalogue\Jobs\ImportColorValues;
use Eclipse\Catalogue\Models\Property;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\ListRecords\Concerns\Translatable;

class ListPropertyValues extends ListRecords
{
    use Translatable;

    protected static string $resource = PropertyValueResource::class;

    public ?Property $property = null;

    public function mount(): void
    {
        parent::mount();

        if (request()->has('property')) {
            $this->property = Property::find(request('property'));

            if ($this->property && $this->property->type === PropertyType::CUSTOM->value) {
                abort(404);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            LocaleSwitcher::make(),
            CreateAction::make()
                ->modalWidth('lg')
                ->modalHeading(__('eclipse-catalogue::property-value.modal.create_heading'))
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

                    if ($this->property && $this->property->isColorType()) {
                        $colorGroup = PropertyValueResource::buildColorGroupSchema();
                        array_splice($schema, 1, 0, $colorGroup);
                    }

                    return $form->components($schema)->columns(1);
                })
                ->mutateDataUsing(function (array $data): array {
                    // Ensure property_id is set from the page state
                    if (empty($data['property_id']) && $this->property) {
                        $data['property_id'] = $this->property->id;
                    }

                    return $data;
                }),
        ];

        if ($this->property && $this->property->type === PropertyType::COLOR->value) {
            $actions[] = Action::make('import')
                ->label(__('eclipse-catalogue::property-value.actions.import'))
                ->icon('heroicon-o-arrow-up-tray')
                ->modalWidth('lg')
                ->modalHeading(__('eclipse-catalogue::property-value.modal.import_heading'))
                ->schema([
                    FileUpload::make('file')
                        ->label(__('eclipse-catalogue::property-value.fields.import_file'))
                        ->helperText(new HtmlString(__('eclipse-catalogue::property-value.help_text.import_file')))
                        ->acceptedFileTypes(['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'text/csv'])
                        ->required()
                        ->disk('local')
                        ->directory('temp/color-imports'),
                ])
                ->action(function (array $data): void {
                    ImportColorValues::dispatch($data['file'], $this->property->id);
                });
        }

        return $actions;
    }

    public function getTitle(): string
    {
        return $this->property
            ? __('eclipse-catalogue::property-value.pages.title.with_property', ['property' => $this->property->name])
            : __('eclipse-catalogue::property-value.pages.title.default');
    }

    public function getBreadcrumbs(): array
    {
        if ($this->property) {
            return [
                PropertyResource::getUrl('index') => __('eclipse-catalogue::property-value.pages.breadcrumbs.properties'),
                PropertyResource::getUrl('edit', ['record' => $this->property]) => $this->property->name,
                request()->url() => __('eclipse-catalogue::property-value.pages.breadcrumbs.list'),
            ];
        }

        return [
            PropertyValueResource::getUrl('index') => __('eclipse-catalogue::property-value.pages.title.default'),
            request()->url() => __('eclipse-catalogue::property-value.pages.breadcrumbs.list'),
        ];
    }

    public function getTable(): Table
    {
        return parent::getTable()
            ->reorderable('sort', $this->property?->enable_sorting)
            ->defaultSort($this->property?->enable_sorting ? 'sort' : 'value')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering) => $action
                    ->button()
                    ->label($isReordering ? 'Disable reordering' : 'Enable reordering')
                    ->icon($isReordering ? 'heroicon-o-x-mark' : 'heroicon-o-arrows-up-down')
                    ->color($isReordering ? 'danger' : 'primary')
                    ->extraAttributes(['class' => 'reorder-trigger'])
            );
    }
}
