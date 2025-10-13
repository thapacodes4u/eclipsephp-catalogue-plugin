<?php

namespace Eclipse\Catalogue\Filament\Resources\GroupResource\RelationManagers;

use Eclipse\Catalogue\Filament\Resources\ProductResource;
use Eclipse\Catalogue\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Product')
                    ->searchable(),
                TextColumn::make('code')
                    ->label('Code')
                    ->copyable()
                    ->toggleable(),
            ])
            ->defaultSort('pim_group_has_product.sort')
            ->reorderable('pim_group_has_product.sort')
            ->reorderRecordsTriggerAction(
                fn (Action $action, bool $isReordering) => $action
                    ->button()
                    ->label($isReordering ? 'Disable reordering' : 'Enable reordering')
                    ->icon($isReordering ? 'heroicon-o-x-mark' : 'heroicon-o-arrows-up-down')
                    ->color($isReordering ? 'danger' : 'primary')
            )
            ->paginated(false)
            ->headerActions([
                Action::make('add_product')
                    ->label('Add product')
                    ->icon('heroicon-o-plus')
                    ->modalHeading('Add Product to Group')
                    ->modalSubmitActionLabel('Add Product')
                    ->modalCancelActionLabel('Cancel')
                    ->schema([
                        Select::make('product_id')
                            ->label('Select product')
                            ->options(function () {
                                $group = $this->getOwnerRecord();
                                $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');
                                $currentTenant = Filament::getTenant();

                                $query = Product::query();

                                if ($currentTenant) {
                                    $query->whereHas('productData', function ($q) use ($tenantFK, $currentTenant) {
                                        $q->where($tenantFK, $currentTenant->id);
                                    });
                                }

                                // Exclude products already in this group
                                $existingProductIds = $group->products()->pluck('catalogue_products.id')->toArray();
                                $query->whereNotIn('catalogue_products.id', $existingProductIds);

                                return $query->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $group = $this->getOwnerRecord();
                        $product = Product::find($data['product_id']);

                        if ($product && ! $group->hasProduct($product)) {
                            $group->addProduct($product);

                            Notification::make()
                                ->title('Product added to group')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->recordActions([
                Action::make('edit_product')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->url(fn ($record): string => ProductResource::getUrl('edit', ['record' => $record->id]))
                    ->openUrlInNewTab(),

                Action::make('move_product')
                    ->label('Reorder')
                    ->icon('heroicon-o-arrows-up-down')
                    ->modalHeading(fn ($record) => 'Reorder: '.$record->name)
                    ->modalSubmitActionLabel('Move Product')
                    ->modalCancelActionLabel('Cancel')
                    ->schema(function ($record, $livewire) {
                        return [
                            Placeholder::make('moving_info')
                                ->label('You are moving')
                                ->content($record->name),

                            Select::make('reference_id')
                                ->label('Place relative to')
                                ->options(
                                    $livewire->getOwnerRecord()
                                        ->products()
                                        ->pluck('name', 'id')
                                        ->except($record->id)
                                )
                                ->searchable()
                                ->reactive()
                                ->required(),

                            Radio::make('position')
                                ->label('Position')
                                ->options([
                                    'before' => 'Before selected product',
                                    'after' => 'After selected product',
                                ])
                                ->default('before')
                                ->inline()
                                ->reactive(),

                            Placeholder::make('preview')
                                ->label('Result')
                                ->content(function (callable $get, $livewire) use ($record) {
                                    $refId = $get('reference_id');
                                    $position = $get('position') ?? 'before';

                                    if (! $refId) {
                                        return 'Select a product to see the result.';
                                    }

                                    $refName = $livewire->getOwnerRecord()
                                        ->products()
                                        ->where('catalogue_products.id', $refId)
                                        ->value('name');

                                    if (! $refName) {
                                        return 'Select a valid product.';
                                    }

                                    return sprintf(
                                        '%s will be moved %s %s',
                                        $record->name,
                                        $position,
                                        $refName
                                    );
                                }),
                        ];
                    })
                    ->action(function ($record, array $data) {
                        $group = $this->getOwnerRecord();

                        $reference = $group->products()
                            ->where('catalogue_products.id', $data['reference_id'])
                            ->firstOrFail();

                        if ($data['position'] === 'before') {
                            $previous = $group->products()
                                ->wherePivot('sort', '<', $reference->pivot->sort)
                                ->orderByDesc('pim_group_has_product.sort')
                                ->first();

                            $newSort = $previous
                                ? intdiv($previous->pivot->sort + $reference->pivot->sort, 2)
                                : $reference->pivot->sort - 1000;
                        } else {
                            $next = $group->products()
                                ->wherePivot('sort', '>', $reference->pivot->sort)
                                ->orderBy('pim_group_has_product.sort')
                                ->first();

                            $newSort = $next
                                ? intdiv($reference->pivot->sort + $next->pivot->sort, 2)
                                : $reference->pivot->sort + 1000;
                        }

                        $group->updateProductSort($record, $newSort);
                    }),

                Action::make('remove_product')
                    ->label('Remove')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Remove Product from Group')
                    ->modalDescription('Are you sure you want to remove this product from the group?')
                    ->modalSubmitActionLabel('Remove Product')
                    ->action(function ($record) {
                        $group = $this->getOwnerRecord();
                        $group->removeProduct($record);

                        Notification::make()
                            ->title('Product removed from group')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('remove_products')
                        ->label('Remove from Group')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Remove Products from Group')
                        ->modalDescription('Are you sure you want to remove the selected products from this group?')
                        ->modalSubmitActionLabel('Remove Products')
                        ->action(function ($records) {
                            $group = $this->getOwnerRecord();
                            $removedCount = 0;

                            foreach ($records as $record) {
                                if ($group->hasProduct($record)) {
                                    $group->removeProduct($record);
                                    $removedCount++;
                                }
                            }

                            Notification::make()
                                ->title("Removed {$removedCount} products from group")
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }
}
