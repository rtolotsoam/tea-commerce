<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\PurchaseItem;
use App\Models\PurchaseCondition;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CsvImportService
{
    public function importPurchases(string $filePath): array
    {
        $data = Excel::toArray([], $filePath)[0];
        $errors = [];
        $imported = 0;

        // Grouper par numéro de commande
        $groupedData = collect($data)->groupBy('purchase_number');

        DB::beginTransaction();

        try {
            foreach ($groupedData as $purchaseNumber => $items) {
                $firstItem = $items->first();

                // Valider le fournisseur
                $supplier = Supplier::where('code', $firstItem['supplier_code'])->first();
                if (!$supplier) {
                    $errors[] = "Fournisseur introuvable: {$firstItem['supplier_code']}";
                    continue;
                }

                // Créer la commande
                $purchase = Purchase::create([
                    'purchase_number' => $purchaseNumber,
                    'supplier_id' => $supplier->id,
                    'order_date' => $firstItem['order_date'],
                    'delivery_date' => $firstItem['delivery_date'],
                    'status' => 'draft',
                    'currency' => $supplier->currency,
                ]);

                // Ajouter les lignes
                foreach ($items as $item) {
                    $product = Product::where('sku', $item['product_sku'])->first();
                    if (!$product) {
                        $errors[] = "Produit introuvable: {$item['product_sku']}";
                        continue;
                    }

                    $quantity = floatval($item['quantity']);
                    $unitPrice = floatval($item['unit_price']);
                    $discountPercent = floatval($item['discount_percent'] ?? 0);
                    $taxRate = floatval($item['tax_rate'] ?? 20);

                    $subtotal = $quantity * $unitPrice;
                    $discountAmount = $subtotal * ($discountPercent / 100);
                    $subtotal -= $discountAmount;

                    PurchaseItem::create([
                        'purchase_id' => $purchase->id,
                        'product_id' => $product->id,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount,
                        'tax_rate' => $taxRate,
                        'subtotal' => $subtotal,
                        'total' => $subtotal * (1 + $taxRate / 100),
                    ]);
                }

                $purchase->calculateTotals();
                $imported++;
            }

            DB::commit();

            return [
                'success' => true,
                'imported' => $imported,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $errors
            ];
        }
    }

    public function importProducts(string $filePath): array
    {
        $data = Excel::toArray([], $filePath)[0];
        $errors = [];
        $imported = 0;

        DB::beginTransaction();

        try {
            foreach ($data as $row) {
                if (empty($row['sku']) || empty($row['title'])) {
                    $errors[] = "Ligne incomplète : SKU ou titre manquant.";
                    continue;
                }

                $product = Product::updateOrCreate(
                    ['sku' => $row['sku']],
                    [
                        'title' => $row['title'],
                        'description' => $row['description'] ?? '',
                        'vendor' => $row['vendor'] ?? null,
                        'price' => floatval($row['price'] ?? 0),
                        'tax_rate' => floatval($row['tax_rate'] ?? 0),
                    ]
                );

                $imported++;
            }

            DB::commit();

            return [
                'success' => true,
                'imported' => $imported,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $errors
            ];
        }
    }

    public function importPurchaseConditions(string $filePath): array
    {
        $data = Excel::toArray([], $filePath)[0];
        $errors = [];
        $imported = 0;

        DB::beginTransaction();

        try {
            foreach ($data as $row) {
                if (empty($row['supplier_code']) || empty($row['product_sku'])) {
                    $errors[] = "Ligne invalide (code fournisseur ou SKU manquant)";
                    continue;
                }

                $supplier = Supplier::where('code', $row['supplier_code'])->first();
                $product = Product::where('sku', $row['product_sku'])->first();

                if (!$supplier || !$product) {
                    $errors[] = "Fournisseur ou produit introuvable : {$row['supplier_code']} / {$row['product_sku']}";
                    continue;
                }

                PurchaseCondition::updateOrCreate(
                    [
                        'supplier_id' => $supplier->id,
                        'product_id' => $product->id
                    ],
                    [
                        'unit_price' => floatval($row['unit_price'] ?? 0),
                        'minimum_quantity' => intval($row['minimum_quantity'] ?? 1),
                        'discount_percent' => floatval($row['discount_percent'] ?? 0),
                        'delivery_time' => intval($row['delivery_time'] ?? 0),
                    ]
                );

                $imported++;
            }

            DB::commit();

            return [
                'success' => true,
                'imported' => $imported,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $errors
            ];
        }
    }
}
