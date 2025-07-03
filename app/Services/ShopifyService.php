<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Models\Product;
use App\Models\ShopifyPrice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use GuzzleHttp\Exception\GuzzleException;
use App\Services\MarginCalculationService;

class ShopifyService
{
    private Client $client;
    private string $shop;
    private string $accessToken;
    private string $apiVersion;
    private MarginCalculationService $marginService;

    public function __construct(MarginCalculationService $marginService)
    {
        $this->shop = config('services.shopify.domain');
        $this->accessToken = config('services.shopify.access_token');
        $this->apiVersion = config('services.shopify.api_version', '2024-01');
        $this->marginService = $marginService;

        $this->client = new Client([
            'base_uri' => "https://{$this->shop}/admin/api/{$this->apiVersion}/",
            'headers' => [
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'timeout' => 30,
            'verify' => config('services.shopify.verify_ssl', true),
        ]);
    }

    /**
     * Synchroniser le prix d'un produit spécifique
     */
    public function syncProductPrice(Product $product): ShopifyPrice
    {
        if (!$product->shopify_product_id) {
            throw new \Exception("Pas d'ID Shopify pour le produit {$product->sku}");
        }

        try {
            // Récupérer les données depuis Shopify
            $shopifyData = $this->getProduct($product->shopify_product_id);

            // Trouver le bon variant
            $variant = $this->findVariant($shopifyData, $product->shopify_variant_id);

            if (!$variant) {
                throw new \Exception("Variant non trouvé pour le produit {$product->sku}");
            }

            // Mettre à jour ou créer le prix
            $shopifyPrice = $this->updateOrCreatePrice($product, $variant);

            // Recalculer la marge
            $this->marginService->calculateProductMargin($product);

            // Invalider le cache
            $this->invalidateProductCache($product->id);

            return $shopifyPrice;

        } catch (GuzzleException $e) {
            Log::error("Erreur Shopify pour le produit {$product->sku}: " . $e->getMessage());
            throw new \Exception("Erreur de communication avec Shopify: " . $e->getMessage());
        }
    }

    /**
     * Synchroniser tous les produits
     */
    public function syncAllProducts(): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'skipped' => 0,
            'details' => []
        ];

        try {
            // Récupérer tous les produits depuis Shopify par batch
            $page = 1;
            $limit = 250;

            do {
                $response = $this->client->get('products.json', [
                    'query' => [
                        'limit' => $limit,
                        'page' => $page,
                        'fields' => 'id,title,variants,status,vendor',
                        'status' => 'active'
                    ]
                ]);

                $data = json_decode($response->getBody(), true);
                $products = $data['products'] ?? [];

                foreach ($products as $shopifyProduct) {
                    $this->processSingleProduct($shopifyProduct, $results);
                }

                $page++;
            } while (count($products) === $limit);

        } catch (GuzzleException $e) {
            Log::error("Erreur sync globale Shopify: " . $e->getMessage());
            throw new \Exception("Erreur lors de la synchronisation: " . $e->getMessage());
        }

        // Recalculer toutes les marges après la sync
        $this->marginService->calculateAllMargins();

        return $results;
    }

    /**
     * Récupérer un produit depuis Shopify
     */
    public function getProduct(string $productId): array
    {
        $cacheKey = "shopify_product_{$productId}";

        return Cache::remember($cacheKey, 300, function () use ($productId) {
            $response = $this->client->get("products/{$productId}.json", [
                'query' => ['fields' => 'id,title,variants,status,vendor,product_type']
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['product'] ?? [];
        });
    }

    /**
     * Récupérer l'inventaire d'un variant
     */
    public function getInventoryLevel(string $inventoryItemId): ?int
    {
        try {
            $response = $this->client->get('inventory_levels.json', [
                'query' => [
                    'inventory_item_ids' => $inventoryItemId,
                    'limit' => 1
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            $levels = $data['inventory_levels'] ?? [];

            if (!empty($levels)) {
                return $levels[0]['available'] ?? 0;
            }

            return null;

        } catch (GuzzleException $e) {
            Log::warning("Erreur récupération inventaire: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Mettre à jour le prix d'un produit sur Shopify
     */
    public function updateProductPrice(string $variantId, float $price, ?float $compareAtPrice = null): bool
    {
        try {
            $data = [
                'variant' => [
                    'id' => $variantId,
                    'price' => number_format($price, 2, '.', ''),
                ]
            ];

            if ($compareAtPrice !== null) {
                $data['variant']['compare_at_price'] = number_format($compareAtPrice, 2, '.', '');
            }

            $response = $this->client->put("variants/{$variantId}.json", [
                'json' => $data
            ]);

            return $response->getStatusCode() === 200;

        } catch (GuzzleException $e) {
            Log::error("Erreur mise à jour prix Shopify: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Créer un webhook pour les mises à jour de prix
     */
    public function createWebhook(string $topic, string $address): array
    {
        try {
            $response = $this->client->post('webhooks.json', [
                'json' => [
                    'webhook' => [
                        'topic' => $topic,
                        'address' => $address,
                        'format' => 'json',
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['webhook'] ?? [];

        } catch (GuzzleException $e) {
            Log::error("Erreur création webhook: " . $e->getMessage());
            throw new \Exception("Impossible de créer le webhook: " . $e->getMessage());
        }
    }

    /**
     * Lister les webhooks existants
     */
    public function listWebhooks(): array
    {
        try {
            $response = $this->client->get('webhooks.json');
            $data = json_decode($response->getBody(), true);
            return $data['webhooks'] ?? [];

        } catch (GuzzleException $e) {
            Log::error("Erreur liste webhooks: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Supprimer un webhook
     */
    public function deleteWebhook(string $webhookId): bool
    {
        try {
            $response = $this->client->delete("webhooks/{$webhookId}.json");
            return $response->getStatusCode() === 200;

        } catch (GuzzleException $e) {
            Log::error("Erreur suppression webhook: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier la signature d'un webhook
     */
    public function verifyWebhookSignature(string $data, string $hmacHeader): bool
    {
        $secret = config('services.shopify.webhook_secret');
        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $secret, true));

        return hash_equals($calculatedHmac, $hmacHeader);
    }

    /**
     * Rechercher des produits
     */
    public function searchProducts(string $query, int $limit = 50): array
    {
        try {
            $response = $this->client->get('products/search.json', [
                'query' => [
                    'query' => $query,
                    'limit' => $limit,
                    'fields' => 'id,title,variants,vendor'
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['products'] ?? [];

        } catch (GuzzleException $e) {
            Log::error("Erreur recherche Shopify: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtenir le nombre total de produits
     */
    public function getProductsCount(array $filters = []): int
    {
        try {
            $response = $this->client->get('products/count.json', [
                'query' => $filters
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['count'] ?? 0;

        } catch (GuzzleException $e) {
            Log::error("Erreur count Shopify: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Traiter un seul produit Shopify
     */
    private function processSingleProduct(array $shopifyProduct, array &$results): void
    {
        foreach ($shopifyProduct['variants'] as $variant) {
            try {
                // Chercher le produit local par SKU
                $product = Product::where('sku', $variant['sku'])->first();

                if (!$product) {
                    $results['skipped']++;
                    $results['details'][] = [
                        'sku' => $variant['sku'],
                        'status' => 'skipped',
                        'message' => 'Produit non trouvé dans la base locale'
                    ];
                    continue;
                }

                // Mettre à jour les IDs Shopify si nécessaire
                if (!$product->shopify_product_id || !$product->shopify_variant_id) {
                    $product->update([
                        'shopify_product_id' => $shopifyProduct['id'],
                        'shopify_variant_id' => $variant['id'],
                    ]);
                }

                // Mettre à jour le prix
                $this->updateOrCreatePrice($product, $variant);

                $results['success']++;
                $results['details'][] = [
                    'sku' => $variant['sku'],
                    'status' => 'success',
                    'price' => $variant['price']
                ];

            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][] = [
                    'sku' => $variant['sku'] ?? 'unknown',
                    'status' => 'error',
                    'message' => $e->getMessage()
                ];
                Log::error("Erreur sync produit: " . $e->getMessage());
            }
        }
    }

    /**
     * Trouver le bon variant dans les données Shopify
     */
    private function findVariant(array $shopifyProduct, ?string $variantId = null): ?array
    {
        if (!isset($shopifyProduct['variants'])) {
            return null;
        }

        // Si on a un ID de variant, le chercher
        if ($variantId) {
            foreach ($shopifyProduct['variants'] as $variant) {
                if ($variant['id'] == $variantId) {
                    return $variant;
                }
            }
        }

        // Sinon, prendre le premier variant (default)
        return $shopifyProduct['variants'][0] ?? null;
    }

    /**
     * Mettre à jour ou créer le prix Shopify
     */
    private function updateOrCreatePrice(Product $product, array $variant): ShopifyPrice
    {
        return ShopifyPrice::updateOrCreate(
            [
                'product_id' => $product->id,
                'shopify_product_id' => $product->shopify_product_id,
                'shopify_variant_id' => $variant['id'],
            ],
            [
                'selling_price' => $variant['price'],
                'compare_at_price' => $variant['compare_at_price'],
                'currency' => config('services.shopify.currency', 'EUR'),
                'last_sync_at' => now(),
            ]
        );
    }

    /**
     * Invalider le cache d'un produit
     */
    private function invalidateProductCache(int $productId): void
    {
        Cache::forget("shopify_product_{$productId}");
        Cache::tags(['products', "product_{$productId}"])->flush();
    }

    /**
     * Obtenir les statistiques de synchronisation
     */
    public function getSyncStats(): array
    {
        $totalProducts = Product::whereNotNull('shopify_product_id')->count();
        $syncedToday = ShopifyPrice::whereDate('last_sync_at', today())->count();
        $needsSync = ShopifyPrice::where('last_sync_at', '<', now()->subHours(4))
            ->orWhereNull('last_sync_at')
            ->count();

        return [
            'total_products' => $totalProducts,
            'synced_today' => $syncedToday,
            'needs_sync' => $needsSync,
            'last_sync' => ShopifyPrice::max('last_sync_at'),
        ];
    }

    /**
     * Exporter les prix vers Shopify (bulk)
     */
    public function exportPricesToShopify(Collection $products): array
    {
        $results = ['success' => 0, 'errors' => 0];

        foreach ($products as $product) {
            if (!$product->shopify_variant_id || !$product->marginAnalysis) {
                continue;
            }

            try {
                // Calculer le prix de vente basé sur la marge souhaitée
                $targetMargin = config('services.shopify.target_margin', 50);
                $purchasePrice = $product->marginAnalysis->purchase_price;
                $sellingPrice = $purchasePrice / (1 - $targetMargin / 100);

                $success = $this->updateProductPrice(
                    $product->shopify_variant_id,
                    round($sellingPrice, 2)
                );

                if ($success) {
                    $results['success']++;
                } else {
                    $results['errors']++;
                }

            } catch (\Exception $e) {
                $results['errors']++;
                Log::error("Erreur export prix: " . $e->getMessage());
            }
        }

        return $results;
    }
}
