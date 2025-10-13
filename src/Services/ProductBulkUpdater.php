<?php

namespace Eclipse\Catalogue\Services;

use Carbon\Carbon;
use Eclipse\Catalogue\Models\Group;
use Eclipse\Catalogue\Models\Product\Price as ProductPrice;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductBulkUpdater
{
    /**
     * Apply the bulk updates to the given products.
     *
     * @param  array  $data  The data to apply the updates to.
     * @param  iterable  $products  The products to apply the updates to.
     * @return array The result of the updates.
     */
    public function apply(array $data, iterable $products): array
    {
        $successCount = 0;
        $errors = [];

        $groupAddIds = collect($data['groups_add_ids'] ?? [])->filter()->values()->all();
        $groupRemoveIds = collect($data['groups_remove_ids'] ?? [])->filter()->values()->all();
        $coverImage = $data['cover_image'] ?? null;
        $image1 = $data['image_1'] ?? null;
        $image2 = $data['image_2'] ?? null;
        $image3 = $data['image_3'] ?? null;
        $shouldUpdateCategories = array_key_exists('category_id', $data) && $data['category_id'] !== '__no_change__';
        $bulkCategoryId = array_key_exists('category_id', $data)
            ? ($data['category_id'] === '__no_change__' ? null : $data['category_id'])
            : null;
        $shouldUpdateType = array_key_exists('product_type_id', $data) && $data['product_type_id'] !== '__no_change__';
        $bulkTypeId = array_key_exists('product_type_id', $data)
            ? ($data['product_type_id'] === '__no_change__' ? null : $data['product_type_id'])
            : null;
        $shouldUpdateFreeDelivery = array_key_exists('free_delivery', $data) && $data['free_delivery'] !== '__no_change__';
        $bulkFreeDelivery = array_key_exists('free_delivery', $data)
            ? (bool) ((string) $data['free_delivery'] === '1')
            : false;
        $shouldUpdateStatus = array_key_exists('product_status_id', $data) && $data['product_status_id'] !== '__no_change__';
        $bulkStatusId = array_key_exists('product_status_id', $data)
            ? ($data['product_status_id'] === '__no_change__' ? null : $data['product_status_id'])
            : null;
        $bulkPriceListId = array_key_exists('price_list_id', $data) ? $data['price_list_id'] : null;
        $bulkPrice = array_key_exists('price', $data) ? $data['price'] : null;
        $bulkValidFrom = array_key_exists('valid_from', $data) ? $data['valid_from'] : null;
        $bulkValidTo = array_key_exists('valid_to', $data) ? $data['valid_to'] : null;
        $bulkTaxIncluded = (bool) ($data['tax_included'] ?? false);

        foreach ($products as $product) {
            try {
                $changed = DB::transaction(function () use (
                    $groupAddIds, $groupRemoveIds,
                    $coverImage, $image1, $image2, $image3,
                    $shouldUpdateCategories, $bulkCategoryId,
                    $shouldUpdateType, $bulkTypeId,
                    $shouldUpdateFreeDelivery, $bulkFreeDelivery,
                    $shouldUpdateStatus, $bulkStatusId,
                    $bulkPriceListId, $bulkPrice, $bulkValidFrom, $bulkValidTo, $bulkTaxIncluded,
                    $product
                ) {
                    $didChange = false;

                    // Groups: infer intent if add/remove arrays provided
                    if (! empty($groupAddIds) || ! empty($groupRemoveIds)) {
                        if (! empty($groupAddIds)) {
                            $validAddIds = Group::query()->forCurrentTenant()->whereIn('id', $groupAddIds)->pluck('id')->all();
                            if (! empty($validAddIds)) {
                                $product->groups()->syncWithoutDetaching($validAddIds);
                                $didChange = true;
                            }
                        }
                        if (! empty($groupRemoveIds)) {
                            $validRemoveIds = Group::query()->forCurrentTenant()->whereIn('id', $groupRemoveIds)->pluck('id')->all();
                            if (! empty($validRemoveIds)) {
                                $product->groups()->detach($validRemoveIds);
                                $didChange = true;
                            }
                        }
                    }

                    // Images: infer intent if any image value provided
                    if ($coverImage || $image1 || $image2 || $image3) {
                        $addMediaFromValue = function ($fileValue) use ($product) {
                            if (! $fileValue) {
                                return null;
                            }
                            if (is_string($fileValue)) {
                                if (! Storage::disk('public')->exists($fileValue)) {
                                    throw new RuntimeException('Temp image not found on public disk: '.$fileValue);
                                }

                                return $product->addMediaFromDisk($fileValue, 'public');
                            }
                            if ($fileValue instanceof UploadedFile) {
                                return $product->addMedia($fileValue->getRealPath());
                            }

                            return null;
                        };

                        if ($coverImage) {
                            $product->getMedia('images')
                                ->filter(fn ($m) => (bool) $m->getCustomProperty('is_cover', false))
                                ->each->delete();

                            $adder = $addMediaFromValue($coverImage);
                            if ($adder) {
                                $adder->preservingOriginal()->withCustomProperties(['is_cover' => true])->toMediaCollection('images');
                                $didChange = true;
                            }
                        }

                        $replaceNthImage = function (int $n, $value) use ($product, &$didChange, $addMediaFromValue) {
                            if (! $value) {
                                return;
                            }
                            $nonCover = $product->getMedia('images')->filter(fn ($m) => ! (bool) $m->getCustomProperty('is_cover', false))->values();
                            $index = $n - 1;
                            if (isset($nonCover[$index])) {
                                $nonCover[$index]->delete();
                            }
                            $adder = $addMediaFromValue($value);
                            if ($adder) {
                                $adder->preservingOriginal()->toMediaCollection('images');
                                $didChange = true;
                            }
                        };

                        $replaceNthImage(1, $image1);
                        $replaceNthImage(2, $image2);
                        $replaceNthImage(3, $image3);
                    }

                    if ($shouldUpdateCategories) {
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');
                        $currentTenant = Filament::getTenant();
                        if ($tenantFK && $currentTenant) {
                            $existing = $product->productData()->where($tenantFK, $currentTenant->id)->first();
                            $currentCategoryId = $existing?->category_id;
                            if ($currentCategoryId !== ($bulkCategoryId !== null ? (int) $bulkCategoryId : null)) {
                                $product->productData()->updateOrCreate(
                                    [$tenantFK => $currentTenant->id],
                                    ['category_id' => $bulkCategoryId]
                                );
                                $didChange = true;
                            }
                        }
                    }

                    if ($shouldUpdateType) {
                        $newTypeId = $bulkTypeId !== null ? (int) $bulkTypeId : null;
                        $currentTypeId = $product->product_type_id;
                        if ($currentTypeId !== $newTypeId) {
                            $product->product_type_id = $newTypeId;
                            $product->save();
                            $didChange = true;
                        }
                    }

                    if ($shouldUpdateFreeDelivery) {
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');
                        $currentTenant = Filament::getTenant();
                        if ($tenantFK && $currentTenant) {
                            $existing = $product->productData()->where($tenantFK, $currentTenant->id)->first();
                            $currentFlag = (bool) ($existing?->has_free_delivery ?? false);
                            if ($currentFlag !== (bool) $bulkFreeDelivery) {
                                $product->productData()->updateOrCreate(
                                    [$tenantFK => $currentTenant->id],
                                    ['has_free_delivery' => (bool) $bulkFreeDelivery]
                                );
                                $didChange = true;
                            }
                        }
                    }

                    if ($shouldUpdateStatus) {
                        $tenantFK = config('eclipse-catalogue.tenancy.foreign_key', 'site_id');
                        $currentTenant = Filament::getTenant();
                        if ($tenantFK && $currentTenant) {
                            $existing = $product->productData()->where($tenantFK, $currentTenant->id)->first();
                            $currentStatusId = $existing?->product_status_id;
                            $newStatusId = $bulkStatusId !== null ? (int) $bulkStatusId : null;
                            if ($currentStatusId !== $newStatusId) {
                                $product->productData()->updateOrCreate(
                                    [$tenantFK => $currentTenant->id],
                                    ['product_status_id' => $bulkStatusId]
                                );
                                $didChange = true;
                            }
                        }
                    }

                    // Prices: infer intent if price list selected and price present
                    if ($bulkPriceListId && $bulkPrice !== null && $bulkValidFrom) {
                        $price = ProductPrice::query()->firstOrNew([
                            'product_id' => $product->id,
                            'price_list_id' => (int) $bulkPriceListId,
                            'valid_from' => Carbon::parse($bulkValidFrom)->toDateString(),
                        ]);

                        $original = $price->exists ? clone $price : null;

                        $price->price = $bulkPrice;
                        $price->valid_to = $bulkValidTo ? Carbon::parse($bulkValidTo)->toDateString() : null;
                        $price->tax_included = (bool) $bulkTaxIncluded;
                        $price->save();

                        if (! $original || $original->price != $price->price || $original->valid_to != $price->valid_to || $original->tax_included != $price->tax_included) {
                            $didChange = true;
                        }
                    }

                    return $didChange;
                });

                if ($changed) {
                    $successCount++;
                }
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                Log::error('Bulk product update failed', [
                    'product_id' => $product->id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Cleanup temp files if we used image updates
        if ($coverImage || $image1 || $image2 || $image3) {
            foreach ([$coverImage, $image1, $image2, $image3] as $val) {
                if (is_string($val)) {
                    $p = storage_path('app/public/'.$val);
                    if (file_exists($p)) {
                        @unlink($p);
                    }
                }
            }
        }

        return [
            'successCount' => $successCount,
            'errors' => $errors,
        ];
    }
}
