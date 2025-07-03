<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use App\Models\PurchaseCondition;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PurchaseConditionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $conditions = [
            // Conditions pour TEA-001 (Sencha)
            ['sku' => 'TEA-001', 'qty_min' => 0, 'qty_max' => 10, 'price' => 65.00, 'discount' => 0],
            ['sku' => 'TEA-001', 'qty_min' => 10, 'qty_max' => 50, 'price' => 65.00, 'discount' => 5],
            ['sku' => 'TEA-001', 'qty_min' => 50, 'qty_max' => null, 'price' => 65.00, 'discount' => 10],

            // Conditions pour TEA-002 (Ceylon)
            ['sku' => 'TEA-002', 'qty_min' => 0, 'qty_max' => 50, 'price' => 38.50, 'discount' => 0],
            ['sku' => 'TEA-002', 'qty_min' => 50, 'qty_max' => 100, 'price' => 38.50, 'discount' => 5],
            ['sku' => 'TEA-002', 'qty_min' => 100, 'qty_max' => null, 'price' => 38.50, 'discount' => 7],

            // Conditions pour TEA-003 (Gunpowder)
            ['sku' => 'TEA-003', 'qty_min' => 0, 'qty_max' => 50, 'price' => 45.00, 'discount' => 0],
            ['sku' => 'TEA-003', 'qty_min' => 50, 'qty_max' => 100, 'price' => 45.00, 'discount' => 5],
            ['sku' => 'TEA-003', 'qty_min' => 100, 'qty_max' => null, 'price' => 45.00, 'discount' => 10],

            // Conditions pour autres produits
            ['sku' => 'TEA-004', 'qty_min' => 0, 'qty_max' => null, 'price' => 78.00, 'discount' => 0],
            ['sku' => 'TEA-005', 'qty_min' => 0, 'qty_max' => null, 'price' => 55.00, 'discount' => 0],
            ['sku' => 'INF-001', 'qty_min' => 0, 'qty_max' => null, 'price' => 12.50, 'discount' => 0],
            ['sku' => 'INF-002', 'qty_min' => 0, 'qty_max' => null, 'price' => 14.00, 'discount' => 0],
            ['sku' => 'INF-003', 'qty_min' => 0, 'qty_max' => null, 'price' => 22.00, 'discount' => 0],
            ['sku' => 'TEA-006', 'qty_min' => 0, 'qty_max' => null, 'price' => 42.00, 'discount' => 0],
            ['sku' => 'TEA-007', 'qty_min' => 0, 'qty_max' => null, 'price' => 52.00, 'discount' => 0],
        ];

        foreach ($conditions as $condition) {
            $product = Product::where('sku', $condition['sku'])->first();

            if ($product) {
                PurchaseCondition::create([
                    'supplier_id' => $product->supplier_id,
                    'product_id' => $product->id,
                    'quantity_min' => $condition['qty_min'],
                    'quantity_max' => $condition['qty_max'],
                    'unit_price' => $condition['price'],
                    'discount_percent' => $condition['discount'],
                    'valid_from' => now()->startOfYear(),
                    'valid_until' => now()->endOfYear(),
                    'is_active' => true,
                ]);
            }
        }
    }
}
