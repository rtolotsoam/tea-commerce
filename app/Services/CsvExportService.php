<?php

namespace App\Services;

use App\Models\Stock;
use League\Csv\Writer;
use App\Models\Purchase;
use App\Models\MarginAnalysis;
use Illuminate\Support\Collection;

class CsvExportService
{
    private StorageService $storage;

    public function __construct(StorageService $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Exporter le rapport des marges
     */
    public function exportMarginReport(): array
    {
        $data = MarginAnalysis::with(['product.category', 'supplier'])
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
            });

        return $this->createCsvFile($data, 'margin_report_' . date('Y-m-d_His') . '.csv');
    }

    /**
     * Exporter le rapport des achats avec marges
     */
    public function exportPurchaseMargins(?int $purchaseId = null): array
    {
        $query = Purchase::with(['items.product.shopifyPrice', 'supplier']);

        if ($purchaseId) {
            $query->where('id', $purchaseId);
        }

        $data = collect();

        $query->get()->each(function ($purchase) use (&$data) {
            foreach ($purchase->items as $item) {
                $sellingPrice = $item->product->shopifyPrice->selling_price ?? 0;
                $purchasePrice = $item->unit_price - ($item->discount_amount / $item->quantity);
                $unitMargin = $sellingPrice - $purchasePrice;
                $marginPercent = $sellingPrice > 0 ? ($unitMargin / $sellingPrice * 100) : 0;

                $data->push([
                    'purchase_number' => $purchase->purchase_number,
                    'order_date' => $purchase->order_date->format('Y-m-d'),
                    'supplier_name' => $purchase->supplier->name,
                    'product_sku' => $item->product->sku,
                    'product_name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'purchase_price' => number_format($purchasePrice, 2),
                    'selling_price' => number_format($sellingPrice, 2),
                    'unit_margin' => number_format($unitMargin, 2),
                    'total_margin' => number_format($unitMargin * $item->quantity, 2),
                    'margin_percent' => number_format($marginPercent, 2) . '%',
                    'status' => $purchase->status,
                ]);
            }
        });

        return $this->createCsvFile($data, 'purchase_margins_' . date('Y-m-d_His') . '.csv');
    }

    /**
     * Exporter l'analyse des stocks
     */
    public function exportStockAnalysis(): array
    {
        $data = Stock::with(['product.supplier'])
            ->get()
            ->map(function ($stock) {
                return [
                    'sku' => $stock->product->sku,
                    'product_name' => $stock->product->name,
                    'supplier' => $stock->product->supplier->name ?? 'N/A',
                    'on_hand' => $stock->quantity_on_hand,
                    'reserved' => $stock->quantity_reserved,
                    'available' => $stock->quantity_available,
                    'reorder_point' => $stock->reorder_point,
                    'last_purchase_date' => $stock->last_purchase_date?->format('Y-m-d') ?? '',
                    'avg_cost' => number_format($stock->average_cost, 2),
                    'current_value' => number_format($stock->quantity_on_hand * $stock->average_cost, 2),
                ];
            });

        return $this->createCsvFile($data, 'stock_analysis_' . date('Y-m-d_His') . '.csv');
    }

    /**
     * Créer et sauvegarder un fichier CSV
     */
    private function createCsvFile(Collection $data, string $filename): array
    {
        // Créer le CSV en mémoire
        $csv = Writer::createFromString();

        // Ajouter l'en-tête
        if ($data->isNotEmpty()) {
            $csv->insertOne(array_keys($data->first()));
        }

        // Ajouter les données
        $csv->insertAll($data->toArray());

        // Sauvegarder dans MinIO
        return $this->storage->saveCsvExport($csv->toString(), $filename);
    }
}
