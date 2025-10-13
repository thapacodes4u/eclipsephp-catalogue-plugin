<?php

use Eclipse\Catalogue\Models\PriceList;
use Eclipse\Catalogue\Models\Product;
use Eclipse\Catalogue\Models\Product\Price as ProductPrice;
use Eclipse\World\Models\Currency;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    Currency::create(['id' => 'USD', 'name' => 'US Dollar', 'is_active' => true]);
    Currency::create(['id' => 'EUR', 'name' => 'Euro', 'is_active' => true]);
});

function makeProduct(): Product
{
    return Product::factory()->create();
}

function makePriceList(array $attrs = []): PriceList
{
    return PriceList::factory()->create(array_merge([
        'currency_id' => 'USD',
        'tax_included' => false,
    ], $attrs));
}

test('can create a product price', function () {
    $product = makeProduct();
    $priceList = makePriceList();

    $price = ProductPrice::create([
        'product_id' => $product->id,
        'price_list_id' => $priceList->id,
        'valid_from' => '2025-08-18',
        'valid_to' => null,
        'price' => 12.34567,
        'tax_included' => true,
    ]);

    expect($price)->toBeInstanceOf(ProductPrice::class);
    expect($price->price)->toBeFloat();
    expect($price->tax_included)->toBeTrue();
    expect($price->valid_from)->toBeInstanceOf(Carbon::class);
    expect($price->valid_to)->toBeNull();
});

test('price has product and price list relations', function () {
    $product = makeProduct();
    $priceList = makePriceList();

    $price = ProductPrice::create([
        'product_id' => $product->id,
        'price_list_id' => $priceList->id,
        'valid_from' => '2025-08-18',
        'price' => 9.99,
        'tax_included' => false,
    ]);

    expect($price->product)->toBeInstanceOf(Product::class)
        ->and($price->priceList)->toBeInstanceOf(PriceList::class);
});

test('cannot create duplicate price for same product, price list and date', function () {
    $product = makeProduct();
    $priceList = makePriceList();

    ProductPrice::create([
        'product_id' => $product->id,
        'price_list_id' => $priceList->id,
        'valid_from' => '2025-08-18',
        'price' => 10,
        'tax_included' => false,
    ]);

    // 2nd insert with the same triple key must fail at DB level
    expect(fn () => ProductPrice::create([
        'product_id' => $product->id,
        'price_list_id' => $priceList->id,
        'valid_from' => '2025-08-18',
        'price' => 11,
        'tax_included' => true,
    ]))->toThrow(QueryException::class);
});

test('same date is allowed on different price lists', function () {
    $product = makeProduct();
    $pl1 = makePriceList(['currency_id' => 'USD']);
    $pl2 = makePriceList(['currency_id' => 'USD']);

    ProductPrice::create([
        'product_id' => $product->id,
        'price_list_id' => $pl1->id,
        'valid_from' => '2025-08-18',
        'price' => 10,
        'tax_included' => false,
    ]);

    $p2 = ProductPrice::create([
        'product_id' => $product->id,
        'price_list_id' => $pl2->id,
        'valid_from' => '2025-08-18',
        'price' => 12,
        'tax_included' => false,
    ]);

    expect($p2->exists)->toBeTrue();
});

test('same date is allowed on same price list for different products', function () {
    $p1 = makeProduct();
    $p2 = makeProduct();
    $priceList = makePriceList();

    ProductPrice::create([
        'product_id' => $p1->id,
        'price_list_id' => $priceList->id,
        'valid_from' => '2025-08-18',
        'price' => 10,
        'tax_included' => false,
    ]);

    $ok = ProductPrice::create([
        'product_id' => $p2->id,
        'price_list_id' => $priceList->id,
        'valid_from' => '2025-08-18',
        'price' => 13,
        'tax_included' => true,
    ]);

    expect($ok->exists)->toBeTrue();
});

test('stores price with up to 5 decimal places', function () {
    $product = makeProduct();
    $priceList = makePriceList();

    $p = ProductPrice::create([
        'product_id' => $product->id,
        'price_list_id' => $priceList->id,
        'valid_from' => '2025-08-18',
        'price' => 123.45678,
        'tax_included' => true,
    ])->fresh();

    expect($p->price)->toEqual(123.45678);
});

test('(soft) deleting product does not remove prices', function () {
    $product = makeProduct();
    $priceList = makePriceList();

    $price = ProductPrice::create([
        'product_id' => $product->id,
        'price_list_id' => $priceList->id,
        'valid_from' => '2025-08-18',
        'price' => 5,
        'tax_included' => false,
    ]);

    $product->delete();

    expect(ProductPrice::find($price->id))->not->toBeNull();
});
