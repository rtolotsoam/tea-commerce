<?php

namespace App\Repositories;

use App\Models\ScrapedData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ScrapedDataRepository
{
    /**
     * Obtenir les dernières données pour chaque produit d'un fournisseur
     */
    public function getLatestForSupplier(int $supplierId): Collection
    {
        return Cache::remember("latest_scraped_{$supplierId}", 300, function () use ($supplierId) {
            return ScrapedData::select('scraped_data.*')
                ->join(
                    DB::raw('(SELECT product_id, MAX(scraped_at) as max_date
                              FROM scraped_data
                              WHERE supplier_id = :supplier_id
                              AND product_id IS NOT NULL
                              GROUP BY product_id) as latest'),
                    function ($join) {
                        $join->on('scraped_data.product_id', '=', 'latest.product_id')
                             ->on('scraped_data.scraped_at', '=', 'latest.max_date');
                    }
                )
                ->where('scraped_data.supplier_id', $supplierId)
                ->with(['product', 'supplier'])
                ->setBindings([$supplierId], 'join')
                ->get();
        });
    }

    /**
     * Obtenir l'historique des prix pour un produit
     */
    public function getPriceHistory(int $productId, int $days = 30): Collection
    {
        $cacheKey = "price_history_{$productId}_{$days}";

        return Cache::remember($cacheKey, 3600, function () use ($productId, $days) {
            return ScrapedData::where('product_id', $productId)
                ->where('scraped_at', '>=', now()->subDays($days))
                ->orderBy('scraped_at')
                ->get()
                ->map(function ($data) {
                    return [
                        'date' => $data->scraped_at->format('Y-m-d'),
                        'time' => $data->scraped_at->format('H:i'),
                        'price' => $data->price_in_eur,
                        'currency' => $data->currency,
                        'availability' => $data->normalized_availability,
                        'stock_quantity' => $data->stock_quantity,
                        'source' => $data->source_type,
                    ];
                });
        });
    }

    /**
     * Nettoyer les anciennes données
     */
    public function cleanOldData(int $daysToKeep = 90): int
    {
        // Invalider le cache
        Cache::tags(['scraped_data'])->flush();

        return ScrapedData::where('scraped_at', '<', now()->subDays($daysToKeep))->delete();
    }

    /**
     * Obtenir les données récentes par type de source
     */
    public function getRecentBySourceType(string $type, int $hours = 24): Collection
    {
        return ScrapedData::where('source_type', $type)
            ->where('scraped_at', '>=', now()->subHours($hours))
            ->with(['product', 'supplier'])
            ->orderBy('scraped_at', 'desc')
            ->get();
    }

    /**
     * Obtenir les produits avec variations de prix importantes
     */
    public function getProductsWithPriceAlerts(float $threshold = 10, int $days = 7): Collection
    {
        $subQuery = ScrapedData::select('product_id')
            ->selectRaw('MIN(price) as min_price')
            ->selectRaw('MAX(price) as max_price')
            ->selectRaw('AVG(price) as avg_price')
            ->where('scraped_at', '>=', now()->subDays($days))
            ->whereNotNull('product_id')
            ->groupBy('product_id')
            ->having(DB::raw('((MAX(price) - MIN(price)) / AVG(price) * 100)'), '>=', $threshold);

        return DB::table(DB::raw("({$subQuery->toSql()}) as price_variations"))
            ->mergeBindings($subQuery->getQuery())
            ->join('products', 'price_variations.product_id', '=', 'products.id')
            ->select('products.*', 'price_variations.*')
            ->get();
    }

    /**
     * Obtenir les statistiques par fournisseur
     */
    public function getSupplierStats(int $days = 30): Collection
    {
        return DB::table('scraped_data')
            ->join('suppliers', 'scraped_data.supplier_id', '=', 'suppliers.id')
            ->select('suppliers.id', 'suppliers.name', 'suppliers.code')
            ->selectRaw('COUNT(DISTINCT scraped_data.product_id) as products_count')
            ->selectRaw('COUNT(*) as total_scrapes')
            ->selectRaw('MAX(scraped_at) as last_scrape')
            ->selectRaw('AVG(price) as avg_price')
            ->where('scraped_at', '>=', now()->subDays($days))
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.code')
            ->orderBy('total_scrapes', 'desc')
            ->get();
    }

    /**
     * Rechercher des produits scrapés
     */
    public function searchProducts(string $query, ?int $supplierId = null): Collection
    {
        $search = ScrapedData::where(function ($q) use ($query) {
            $q->where('product_name', 'LIKE', "%{$query}%")
              ->orWhere('supplier_ref', 'LIKE', "%{$query}%");
        })
            ->with(['product', 'supplier']);

        if ($supplierId) {
            $search->where('supplier_id', $supplierId);
        }

        return $search->orderBy('scraped_at', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Obtenir le rapport de disponibilité
     */
    public function getAvailabilityReport(?int $supplierId = null): array
    {
        $query = ScrapedData::select('supplier_id')
            ->selectRaw('COUNT(DISTINCT product_id) as total_products')
            ->selectRaw('SUM(CASE WHEN normalized_availability = ? THEN 1 ELSE 0 END) as in_stock', [ScrapedData::AVAILABILITY_IN_STOCK])
            ->selectRaw('SUM(CASE WHEN normalized_availability = ? THEN 1 ELSE 0 END) as out_of_stock', [ScrapedData::AVAILABILITY_OUT_OF_STOCK])
            ->selectRaw('SUM(CASE WHEN normalized_availability = ? THEN 1 ELSE 0 END) as limited_stock', [ScrapedData::AVAILABILITY_LIMITED])
            ->whereDate('scraped_at', today())
            ->groupBy('supplier_id');

        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        $data = $query->get();

        return [
            'by_supplier' => $data,
            'totals' => [
                'total_products' => $data->sum('total_products'),
                'in_stock' => $data->sum('in_stock'),
                'out_of_stock' => $data->sum('out_of_stock'),
                'limited_stock' => $data->sum('limited_stock'),
            ]
        ];
    }
}
