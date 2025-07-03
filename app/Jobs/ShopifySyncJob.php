<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\ShopifyService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ShopifySyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 3600; // 1 heure
    public $tries = 3;

    public function __construct(
        public ?Product $product,
        public string $type,
        public string $jobId
    ) {
    }

    public function handle(ShopifyService $shopifyService): void
    {
        try {
            Cache::put("job_{$this->jobId}", [
                'status' => 'processing',
                'progress' => 0,
                'started_at' => now()
            ], now()->addHours(4));

            if ($this->product) {
                // Sync un seul produit
                $shopifyPrice = $shopifyService->syncProductPrice($this->product);

                Cache::put("job_{$this->jobId}", [
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => [
                        'product' => $this->product->sku,
                        'price' => $shopifyPrice->selling_price,
                        'synced_at' => $shopifyPrice->last_sync_at
                    ],
                    'completed_at' => now()
                ], now()->addHours(4));
            } else {
                // Sync globale
                $results = $shopifyService->syncAllProducts();

                Cache::put("job_{$this->jobId}", [
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => $results,
                    'completed_at' => now()
                ], now()->addHours(4));
            }

        } catch (\Exception $e) {
            Log::error("Erreur ShopifySyncJob: " . $e->getMessage());

            Cache::put("job_{$this->jobId}", [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now()
            ], now()->addHours(4));

            throw $e;
        }
    }
}
