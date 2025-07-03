<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Services\ShopifyService;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class ShopifyApiController extends Controller
{
    public function __construct(
        private ShopifyService $shopifyService
    ) {
    }

    /**
     * Synchroniser via API
     */
    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:prices,inventory,all',
            'product_sku' => 'nullable|exists:products,sku'
        ]);

        try {
            if ($request->product_sku) {
                // Sync un seul produit
                $product = Product::where('sku', $request->product_sku)->firstOrFail();
                $shopifyPrice = $this->shopifyService->syncProductPrice($product);

                return response()->json([
                    'success' => true,
                    'message' => 'Produit synchronisé',
                    'data' => [
                        'sku' => $product->sku,
                        'price' => $shopifyPrice->selling_price,
                        'currency' => $shopifyPrice->currency,
                        'last_sync' => $shopifyPrice->last_sync_at
                    ]
                ]);
            }

            // Sync globale
            $results = $this->shopifyService->syncAllProducts();

            return response()->json([
                'success' => true,
                'message' => 'Synchronisation complète terminée',
                'data' => $results
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques
     */
    public function stats(): JsonResponse
    {
        $stats = $this->shopifyService->getSyncStats();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Rechercher des produits Shopify
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:3',
            'limit' => 'nullable|integer|min:1|max:250'
        ]);

        $products = $this->shopifyService->searchProducts(
            $request->query('query'),
            $request->limit ?? 50
        );

        return response()->json([
            'success' => true,
            'data' => $products,
            'count' => count($products)
        ]);
    }
}
