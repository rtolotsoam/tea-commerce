<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ShopifyPrice;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ShopifyPriceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $prices = [
            'TEA-001' => ['price' => 129.90, 'compare' => 149.90],
            'TEA-002' => ['price' => 75.00, 'compare' => 85.00],
            'TEA-003' => ['price' => 89.90, 'compare' => 99.90],
            'TEA-004' => ['price' => 159.90, 'compare' => null],
            'TEA-005' => ['price' => 109.90, 'compare' => 119.90],
            'TEA-006' => ['price' => 85.00, 'compare' => 95.00],
            'TEA-007' => ['price' => 105.00, 'compare' => null],
            'INF-001' => ['price' => 24.90, 'compare' => 29.90],
            'INF-002' => ['price' => 27.90, 'compare' => null],
            'INF-003' => ['price' => 44.90, 'compare' => 49.90],
        ];

        foreach ($prices as $sku => $priceData) {
            $product = Product::where('sku', $sku)->first();

            if ($product && $product->shopify_product_id) {
                ShopifyPrice::create([
                    'product_id' => $product->id,
                    'shopify_product_id' => $product->shopify_product_id,
                    'shopify_variant_id' => $product->shopify_variant_id,
                    'selling_price' => $priceData['price'],
                    'compare_at_price' => $priceData['compare'],
                    'currency' => 'EUR',
                    'last_sync_at' => now(),
                ]);
            }
        }
    }
}
