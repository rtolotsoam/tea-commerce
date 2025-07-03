<?php

namespace App\Services;

use App\Models\Product;
use App\Models\MarginAnalysis;
use Illuminate\Support\Facades\DB;

class MarginCalculationService
{
    public function calculateAllMargins(): void
    {
        $products = Product::with(['stock', 'shopifyPrice', 'supplier'])
            ->where('is_active', true)
            ->get();

        DB::transaction(function () use ($products) {
            foreach ($products as $product) {
                $this->calculateProductMargin($product);
            }
        });
    }

    public function calculateProductMargin(Product $product): ?MarginAnalysis
    {
        if (!$product->stock || !$product->shopifyPrice) {
            return null;
        }

        $purchasePrice = $product->stock->average_cost ?? $product->stock->last_purchase_price ?? 0;
        $sellingPrice = $product->shopifyPrice->selling_price ?? 0;

        return MarginAnalysis::updateOrCreate(
            ['product_id' => $product->id],
            [
                'supplier_id' => $product->supplier_id,
                'purchase_price' => $purchasePrice,
                'selling_price' => $sellingPrice,
                'stock_quantity' => $product->stock->quantity_available ?? 0,
            ]
        );
    }

    public function exportMarginReport(): array
    {
        return MarginAnalysis::with(['product.category', 'supplier'])
            ->get()
            ->map(function ($margin) {
                return [
                    'category' => $margin->product->category->name ?? 'N/A',
                    'product_sku' => $margin->product->sku,
                    'product_name' => $margin->product->name,
                    'supplier' => $margin->supplier->name,
                    'stock_qty' => $margin->stock_quantity,
                    'purchase_price' => number_format($margin->purchase_price, 2),
                    'selling_price' => number_format($margin->selling_price, 2),
                    'margin_amount' => number_format($margin->margin_amount, 2),
                    'margin_percent' => number_format($margin->margin_percent, 2) . '%',
                    'potential_profit' => number_format($margin->potential_profit, 2),
                ];
            })
            ->toArray();
    }
}
