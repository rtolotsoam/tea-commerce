<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ScrapedData;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ScrapedDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $products = Product::with('supplier')->get();

        foreach ($products as $product) {
            // Simuler des données scrapées avec variations de prix
            $basePrice = $product->purchaseConditions()->first()->unit_price ?? 50;
            $variation = rand(-5, 5) / 100; // -5% à +5%
            $scrapedPrice = $basePrice * (1 + $variation);

            ScrapedData::create([
                'supplier_id' => $product->supplier_id,
                'product_id' => $product->id,
                'source_url' => "https://supplier{$product->supplier_id}.com/products/{$product->supplier_ref}",
                'source_type' => rand(0, 1) ? 'scraping' : 'api',
                'supplier_ref' => $product->supplier_ref,
                'product_name' => $product->name,
                'price' => $scrapedPrice,
                'currency' => 'EUR',
                'availability' => rand(0, 100) > 20 ? 'En stock' : 'Stock limité',
                'stock_quantity' => rand(0, 500),
                'raw_data' => json_encode([
                    'last_update' => now()->toIso8601String(),
                    'supplier' => $product->supplier->name,
                    'original_price' => $basePrice,
                ]),
                'scraped_at' => now()->subHours(rand(1, 24)),
            ]);
        }
    }
}
