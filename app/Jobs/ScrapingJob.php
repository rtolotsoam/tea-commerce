<?php

namespace App\Jobs;

use App\Models\Supplier;
use App\Services\ScrapingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ScrapingJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 1800; // 30 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $supplierCode,
        public string $jobId
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(ScrapingService $scrapingService): void
    {
        try {
            // Mettre à jour le statut
            Cache::put("job_{$this->jobId}", [
                'status' => 'processing',
                'progress' => 0,
                'started_at' => now()
            ], now()->addHours(2));

            if ($this->supplierCode) {
                // Scraper un seul fournisseur
                $supplier = Supplier::where('code', $this->supplierCode)
                    ->where('is_active', true)
                    ->firstOrFail();

                $results = $scrapingService->scrapeSupplierPrices($supplier);

                Cache::put("job_{$this->jobId}", [
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => [
                        'scraped' => count($results),
                        'supplier' => $supplier->name
                    ],
                    'completed_at' => now()
                ], now()->addHours(2));
            } else {
                // Scraper tous les fournisseurs
                $results = $scrapingService->scrapeAllSuppliers();

                Cache::put("job_{$this->jobId}", [
                    'status' => 'completed',
                    'progress' => 100,
                    'result' => $results,
                    'completed_at' => now()
                ], now()->addHours(2));
            }

        } catch (\Exception $e) {
            Log::error("Erreur ScrapingJob: " . $e->getMessage());

            Cache::put("job_{$this->jobId}", [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'completed_at' => now()
            ], now()->addHours(2));

            throw $e;
        }
    }
}
