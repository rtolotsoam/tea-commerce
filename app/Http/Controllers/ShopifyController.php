<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;

class ShopifyController extends Controller
{
    public function __construct(
        private ShopifyService $shopifyService
    ) {
    }

    /**
     * Page de synchronisation
     */
    public function index()
    {
        $stats = $this->shopifyService->getSyncStats();

        return view('shopify.index', compact('stats'));
    }

    /**
     * Synchroniser un produit spécifique
     */
    public function syncProduct(Product $product)
    {
        try {
            $shopifyPrice = $this->shopifyService->syncProductPrice($product);

            return redirect()->back()->with(
                'success',
                "Prix synchronisé: {$shopifyPrice->selling_price} {$shopifyPrice->currency}"
            );

        } catch (\Exception $e) {
            Log::error('Erreur sync Shopify: ' . $e->getMessage());

            return redirect()->back()->with(
                'error',
                'Erreur lors de la synchronisation: ' . $e->getMessage()
            );
        }
    }

    /**
     * Synchroniser tous les produits
     */
    public function syncAll(Request $request)
    {
        try {
            $results = $this->shopifyService->syncAllProducts();

            return response()->json([
                'success' => true,
                'message' => "Synchronisation terminée: {$results['success']} réussis, {$results['errors']} erreurs",
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
     * Webhook endpoint pour les mises à jour Shopify
     */
    public function webhook(Request $request)
    {
        // Vérifier la signature
        $hmacHeader = $request->header('X-Shopify-Hmac-Sha256');
        $data = $request->getContent();

        if (!$this->shopifyService->verifyWebhookSignature($data, $hmacHeader)) {
            return response('Unauthorized', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        $payload = json_decode($data, true);

        Log::info("Webhook Shopify reçu: {$topic}");

        switch ($topic) {
            case 'products/update':
                $this->handleProductUpdate($payload);
                break;

            case 'products/delete':
                $this->handleProductDelete($payload);
                break;

            default:
                Log::warning("Topic webhook non géré: {$topic}");
        }

        return response('OK', 200);
    }

    /**
     * Gérer la mise à jour d'un produit
     */
    private function handleProductUpdate(array $payload): void
    {
        try {
            foreach ($payload['variants'] ?? [] as $variant) {
                $product = Product::where('sku', $variant['sku'])->first();

                if ($product) {
                    $this->shopifyService->syncProductPrice($product);
                }
            }
        } catch (\Exception $e) {
            Log::error('Erreur traitement webhook update: ' . $e->getMessage());
        }
    }

    /**
     * Gérer la suppression d'un produit
     */
    private function handleProductDelete(array $payload): void
    {
        try {
            $shopifyProductId = $payload['id'] ?? null;

            if ($shopifyProductId) {
                Product::where('shopify_product_id', $shopifyProductId)
                    ->update([
                        'shopify_product_id' => null,
                        'shopify_variant_id' => null,
                    ]);
            }
        } catch (\Exception $e) {
            Log::error('Erreur traitement webhook delete: ' . $e->getMessage());
        }
    }
}
