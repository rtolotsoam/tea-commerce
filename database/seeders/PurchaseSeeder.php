<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\PurchaseItem;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class PurchaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Commandes livrées
        $this->createPurchase('PO-2025-001', 'SUPP001', 'delivered', -30, [
            ['sku' => 'TEA-003', 'qty' => 100, 'price' => 45.00, 'discount' => 10],
            ['sku' => 'TEA-004', 'qty' => 20, 'price' => 78.00, 'discount' => 0],
            ['sku' => 'TEA-007', 'qty' => 50, 'price' => 52.00, 'discount' => 0],
        ]);

        $this->createPurchase('PO-2025-002', 'SUPP002', 'delivered', -20, [
            ['sku' => 'TEA-002', 'qty' => 150, 'price' => 38.50, 'discount' => 7],
            ['sku' => 'TEA-006', 'qty' => 80, 'price' => 42.00, 'discount' => 0],
        ]);

        $this->createPurchase('PO-2025-003', 'SUPP003', 'delivered', -15, [
            ['sku' => 'TEA-001', 'qty' => 60, 'price' => 65.00, 'discount' => 10],
        ]);

        // Commandes en cours
        $this->createPurchase('PO-2025-004', 'SUPP004', 'ordered', -5, [
            ['sku' => 'INF-001', 'qty' => 50, 'price' => 12.50, 'discount' => 0],
            ['sku' => 'INF-002', 'qty' => 40, 'price' => 14.00, 'discount' => 0],
            ['sku' => 'INF-003', 'qty' => 30, 'price' => 22.00, 'discount' => 0],
        ]);

        $this->createPurchase('PO-2025-005', 'SUPP001', 'ordered', -2, [
            ['sku' => 'TEA-003', 'qty' => 75, 'price' => 45.00, 'discount' => 5],
            ['sku' => 'TEA-005', 'qty' => 25, 'price' => 55.00, 'discount' => 0],
        ]);

        // Commande brouillon
        $this->createPurchase('PO-2025-006', 'SUPP002', 'draft', 0, [
            ['sku' => 'TEA-002', 'qty' => 200, 'price' => 38.50, 'discount' => 7],
        ]);
    }

    private function createPurchase($number, $supplierCode, $status, $daysAgo, $items)
    {
        $supplier = Supplier::where('code', $supplierCode)->first();
        $orderDate = now()->addDays($daysAgo);
        $deliveryDate = $status === 'delivered' ? $orderDate->copy()->addDays(10) : null;

        $purchase = Purchase::create([
            'purchase_number' => $number,
            'supplier_id' => $supplier->id,
            'order_date' => $orderDate,
            'delivery_date' => $deliveryDate,
            'status' => $status,
            'currency' => 'EUR',
            'shipping_cost' => rand(50, 200),
            'notes' => "Commande test - {$status}",
        ]);

        foreach ($items as $item) {
            $product = Product::where('sku', $item['sku'])->first();

            if ($product) {
                $quantity = $item['qty'];
                $unitPrice = $item['price'];
                $discountPercent = $item['discount'];
                $subtotal = $quantity * $unitPrice;
                $discountAmount = $subtotal * ($discountPercent / 100);
                $finalSubtotal = $subtotal - $discountAmount;
                $taxRate = 20;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $discountPercent,
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $taxRate,
                    'subtotal' => $finalSubtotal,
                    'total' => $finalSubtotal * (1 + $taxRate / 100),
                    'received_quantity' => $status === 'delivered' ? $quantity : 0,
                ]);
            }
        }

        $purchase->calculateTotals();
    }
}
