<?php

namespace App\Http\Controllers\Api;

use App\Models\Supplier;
use App\Jobs\ScrapingJob;
use App\Models\ScrapedData;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Services\ScrapingService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use App\Repositories\ScrapedDataRepository;

class ScrapingApiController extends Controller
{
    public function __construct(
        private ScrapingService $scrapingService,
        private ScrapedDataRepository $scrapedDataRepository
    ) {
    }

    /**
     * Lancer le scraping
     */
    public function run(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_code' => 'nullable|exists:suppliers,code',
            'force' => 'nullable|boolean',
            'async' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supplierCode = $request->input('supplier_code');
            $force = $request->boolean('force', false);
            $async = $request->boolean('async', true);

            // Si pas de force, vérifier le cache pour éviter les scraping trop fréquents
            if (!$force) {
                $cacheKey = $supplierCode ? "scraping_supplier_{$supplierCode}" : "scraping_all";
                if (Cache::has($cacheKey)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Scraping déjà effectué récemment. Utilisez force=true pour forcer.',
                        'next_available' => Cache::get($cacheKey . '_expires')
                    ], 429);
                }
            }

            if ($async) {
                // Lancer en arrière-plan
                $jobId = Str::uuid()->toString();

                ScrapingJob::dispatch($supplierCode, $jobId)
                    ->onQueue('scraping');

                // Mettre en cache pour éviter les lancements multiples
                $cacheKey = $supplierCode ? "scraping_supplier_{$supplierCode}" : "scraping_all";
                Cache::put($cacheKey, true, now()->addMinutes(30));
                Cache::put($cacheKey . '_expires', now()->addMinutes(30), now()->addMinutes(30));
                Cache::put("job_{$jobId}", ['status' => 'pending', 'progress' => 0], now()->addHours(2));

                return response()->json([
                    'success' => true,
                    'message' => 'Scraping lancé en arrière-plan',
                    'job_id' => $jobId,
                    'status_url' => route('api.jobs.status', $jobId)
                ], 202);
            }

            // Exécution synchrone
            if ($supplierCode) {
                $supplier = Supplier::where('code', $supplierCode)->firstOrFail();
                $results = $this->scrapingService->scrapeSupplierPrices($supplier);
            } else {
                $results = $this->scrapingService->scrapeAllSuppliers();
            }

            return response()->json([
                'success' => true,
                'message' => 'Scraping terminé',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du scraping',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir le statut d'un job
     */
    public function jobStatus(string $jobId): JsonResponse
    {
        $status = Cache::get("job_{$jobId}");

        if (!$status) {
            return response()->json([
                'success' => false,
                'message' => 'Job non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'job_id' => $jobId,
            'status' => $status['status'] ?? 'unknown',
            'progress' => $status['progress'] ?? 0,
            'result' => $status['result'] ?? null,
            'error' => $status['error'] ?? null,
            'started_at' => $status['started_at'] ?? null,
            'completed_at' => $status['completed_at'] ?? null,
        ]);
    }

    /**
     * Obtenir les dernières données scrapées
     */
    public function latest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'supplier_id' => 'nullable|exists:suppliers,id',
            'hours' => 'nullable|integer|min:1|max:168',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $supplierId = $request->input('supplier_id');
            $hours = $request->input('hours', 24);

            $query = ScrapedData::with(['supplier', 'product'])
                ->where('scraped_at', '>=', now()->subHours($hours))
                ->orderBy('scraped_at', 'desc');

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $data = $query->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $data->items(),
                'meta' => [
                    'current_page' => $data->currentPage(),
                    'last_page' => $data->lastPage(),
                    'per_page' => $data->perPage(),
                    'total' => $data->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des données',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyser les variations de prix
     */
    public function priceAnalysis(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'days' => 'nullable|integer|min:1|max:365',
            'threshold' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $productId = $request->input('product_id');
            $supplierId = $request->input('supplier_id');
            $days = $request->input('days', 30);
            $threshold = $request->input('threshold', 10);

            $query = ScrapedData::where('scraped_at', '>=', now()->subDays($days));

            if ($productId) {
                $query->where('product_id', $productId);
            }

            if ($supplierId) {
                $query->where('supplier_id', $supplierId);
            }

            $data = $query->get();

            // Grouper par produit
            $analysis = $data->groupBy('product_id')->map(function ($productData) use ($threshold) {
                $prices = $productData->pluck('price_in_eur');
                $firstPrice = $prices->first();
                $lastPrice = $prices->last();
                $avgPrice = $prices->avg();
                $minPrice = $prices->min();
                $maxPrice = $prices->max();

                $variation = $firstPrice > 0 ? (($lastPrice - $firstPrice) / $firstPrice * 100) : 0;

                return [
                    'product_id' => $productData->first()->product_id,
                    'product_name' => $productData->first()->product?->name,
                    'supplier' => $productData->first()->supplier?->name,
                    'data_points' => $productData->count(),
                    'first_price' => round($firstPrice, 2),
                    'last_price' => round($lastPrice, 2),
                    'avg_price' => round($avgPrice, 2),
                    'min_price' => round($minPrice, 2),
                    'max_price' => round($maxPrice, 2),
                    'variation_percent' => round($variation, 2),
                    'alert' => abs($variation) >= $threshold,
                    'trend' => $variation > 0 ? 'up' : ($variation < 0 ? 'down' : 'stable'),
                    'history' => $productData->map(function ($item) {
                        return [
                            'date' => $item->scraped_at->format('Y-m-d H:i'),
                            'price' => $item->price_in_eur,
                            'availability' => $item->normalized_availability,
                        ];
                    })->values()
                ];
            });

            // Filtrer les alertes si demandé
            if ($request->boolean('alerts_only')) {
                $analysis = $analysis->filter(function ($item) {
                    return $item['alert'];
                });
            }

            return response()->json([
                'success' => true,
                'data' => $analysis->values(),
                'summary' => [
                    'total_products' => $analysis->count(),
                    'products_with_alerts' => $analysis->where('alert', true)->count(),
                    'average_variation' => round($analysis->avg('variation_percent'), 2),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'analyse',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Nettoyer les anciennes données
     */
    public function cleanup(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'days_to_keep' => 'required|integer|min:7|max:365',
            'dry_run' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $daysToKeep = $request->input('days_to_keep');
            $dryRun = $request->boolean('dry_run', false);

            if ($dryRun) {
                $count = ScrapedData::where('scraped_at', '<', now()->subDays($daysToKeep))->count();

                return response()->json([
                    'success' => true,
                    'message' => 'Mode simulation',
                    'would_delete' => $count,
                    'oldest_date' => ScrapedData::min('scraped_at'),
                ]);
            }

            $deleted = $this->scrapedDataRepository->cleanOldData($daysToKeep);

            return response()->json([
                'success' => true,
                'message' => "Nettoyage terminé",
                'deleted' => $deleted,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du nettoyage',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques de scraping
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = Cache::remember('scraping_stats', 300, function () {
                return [
                    'total_records' => ScrapedData::count(),
                    'records_today' => ScrapedData::whereDate('scraped_at', today())->count(),
                    'records_week' => ScrapedData::where('scraped_at', '>=', now()->subWeek())->count(),
                    'suppliers_active' => ScrapedData::distinct('supplier_id')->count('supplier_id'),
                    'products_tracked' => ScrapedData::whereNotNull('product_id')->distinct('product_id')->count('product_id'),
                    'last_scraping' => ScrapedData::max('scraped_at'),
                    'availability_stats' => [
                        'in_stock' => ScrapedData::where('normalized_availability', ScrapedData::AVAILABILITY_IN_STOCK)->count(),
                        'out_of_stock' => ScrapedData::where('normalized_availability', ScrapedData::AVAILABILITY_OUT_OF_STOCK)->count(),
                        'limited' => ScrapedData::where('normalized_availability', ScrapedData::AVAILABILITY_LIMITED)->count(),
                    ],
                    'source_stats' => [
                        'scraping' => ScrapedData::where('source_type', 'scraping')->count(),
                        'api' => ScrapedData::where('source_type', 'api')->count(),
                    ],
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
