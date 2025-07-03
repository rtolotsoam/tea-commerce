<?php

namespace Database\Seeders;

use App\Models\Stock;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class StockSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stockData = [
            'TEA-001' => ['qty' => 45, 'reserved' => 5, 'reorder' => 20, 'location' => 'A1-01'],
            'TEA-002' => ['qty' => 120, 'reserved' => 20, 'reorder' => 50, 'location' => 'A1-02'],
            'TEA-003' => ['qty' => 80, 'reserved' => 10, 'reorder' => 40, 'location' => 'A1-03'],
            'TEA-004' => ['qty' => 15, 'reserved' => 0, 'reorder' => 10, 'location' => 'A2-01'],
            'TEA-005' => ['qty' => 0, 'reserved' => 0, 'reorder' => 10, 'location' => 'A2-02'],
            'TEA-006' => ['qty' => 65, 'reserved' => 15, 'reorder' => 30, 'location' => 'A2-03'],
            'TEA-007' => ['qty' => 40, 'reserved' => 5, 'reorder' => 20, 'location' => 'A2-04'],
            'INF-001' => ['qty' => 5, 'reserved' => 0, 'reorder' => 20, 'location' => 'B1-01'],
            'INF-002' => ['qty' => 8, 'reserved' => 2, 'reorder' => 15, 'location' => 'B1-02'],
            'INF-003' => ['qty' => 12, 'reserved' => 0, 'reorder' => 15, 'location' => 'B1-03'],
        ];

        foreach ($stockData as $sku => $data) {
            $product = Product::where('sku', $sku)->first();

            if ($product) {
                // Récupérer le dernier prix d'achat depuis les commandes livrées
                $lastPurchaseItem = $product->purchaseItems()
                    ->whereHas('purchase', function ($q) {
                        $q->where('status', 'delivered');
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                Stock::updateOrCreate(
                    ['product_id' => $product->id],
                    [
                        'quantity_on_hand' => $data['qty'],
                        'quantity_reserved' => $data['reserved'],
                        'reorder_point' => $data['reorder'],
                        'reorder_quantity' => $data['reorder'] * 2,
                        'location' => $data['location'],
                        'last_purchase_date' => $lastPurchaseItem?->purchase->delivery_date,
                        'last_purchase_price' => $lastPurchaseItem?->unit_price ?? 0,
                        'average_cost' => $lastPurchaseItem?->unit_price ?? 0,
                    ]
                );
            }
        }
    }
}
