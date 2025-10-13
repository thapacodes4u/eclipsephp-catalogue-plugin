<?php

namespace Eclipse\Catalogue\Filament\Resources\ProductResource\RelationManagers;

use Carbon\Carbon;
use Eclipse\Catalogue\Models\PriceList;
use Eclipse\Catalogue\Models\Product\Price;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PricesRelationManager extends RelationManager
{
    protected static string $relationship = 'prices';

    protected static ?string $recordTitleAttribute = 'price';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('price_list_id')
                ->label(__('eclipse-catalogue::product.price.fields.price_list'))
                ->relationship('priceList', 'name')
                ->required()
                ->preload()
                ->searchable()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    if (! $state) {
                        return;
                    }
                    $pl = PriceList::query()->select('id', 'tax_included')->find($state);
                    if ($pl) {
                        $set('tax_included', (bool) $pl->tax_included);
                    }
                }),

            TextInput::make('price')
                ->label(__('eclipse-catalogue::product.price.fields.price'))
                ->numeric()
                ->rule('decimal:0,5')
                ->required(),

            DatePicker::make('valid_from')
                ->label(__('eclipse-catalogue::product.price.fields.valid_from'))
                ->native(false)
                ->default(fn () => now())
                ->required()
                ->rule(function (Get $get) {
                    return function (string $attribute, $value, $fail) use ($get) {
                        if (empty($value)) {
                            return;
                        }

                        $productId = $this->getOwnerRecord()->id;
                        $priceListId = $get('price_list_id');
                        if (! $priceListId) {
                            return;
                        }

                        $query = Price::query()
                            ->where('product_id', $productId)
                            ->where('price_list_id', $priceListId)
                            ->whereDate('valid_from', Carbon::parse($value)->toDateString());

                        $current = method_exists($this, 'getMountedTableActionRecord') ? $this->getMountedTableActionRecord() : null;
                        if ($current) {
                            $query->where('id', '!=', $current->id);
                        }

                        if ($query->exists()) {
                            $fail(__('eclipse-catalogue::product.price.validation.unique_body'));
                        }
                    };
                }),

            DatePicker::make('valid_to')
                ->label(__('eclipse-catalogue::product.price.fields.valid_to'))
                ->native(false)
                ->nullable(),

            Checkbox::make('tax_included')
                ->label(__('eclipse-catalogue::product.price.fields.tax_included'))
                ->inline(false)
                ->default(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->columns([
                TextColumn::make('priceList.name')
                    ->label(__('eclipse-catalogue::product.price.fields.price_list'))
                    ->sortable()
                    ->searchable(false)
                    ->toggleable(false),
                TextColumn::make('valid_from')
                    ->label(__('eclipse-catalogue::product.price.fields.valid_from'))
                    ->date()
                    ->sortable()
                    ->toggleable(false),
                TextColumn::make('valid_to')
                    ->label(__('eclipse-catalogue::product.price.fields.valid_to'))
                    ->date()
                    ->sortable()
                    ->toggleable(false),
                TextColumn::make('price')
                    ->label(__('eclipse-catalogue::product.price.fields.price'))
                    ->money('EUR', locale: app()->getLocale())
                    ->toggleable(false),
                IconColumn::make('tax_included')
                    ->label(__('eclipse-catalogue::product.price.fields.tax_included'))
                    ->boolean()
                    ->toggleable(false),
            ])
            ->defaultSort('priceList.name')
            ->defaultSort('valid_from', 'asc')
            ->paginated(false)
            ->filters([])
            ->headerActions([
                CreateAction::make()
                    ->label(__('eclipse-catalogue::product.price.actions.add')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('filament-actions::edit.single.label')),
                DeleteAction::make()
                    ->label(__('filament-actions::delete.single.label')),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
