<?php

namespace App\Console\Commands;

use App\Models\Supplier;
use Illuminate\Console\Command;
use App\Services\ScrapingService;

class ScrapeSupplierPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scrape:prices {supplier? : Code du fournisseur}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scraper les prix des fournisseurs';

    /**
     * Execute the console command.
     */
    public function handle(ScrapingService $scrapingService): int
    {
        $supplierCode = $this->argument('supplier');

        $query = Supplier::where('is_active', true);
        if ($supplierCode) {
            $query->where('code', $supplierCode);
        }

        $suppliers = $query->get();

        if ($suppliers->isEmpty()) {
            $this->error('Aucun fournisseur trouvé');
            return 1;
        }

        $this->info("Scraping de {$suppliers->count()} fournisseur(s)...");

        foreach ($suppliers as $supplier) {
            $this->info("Scraping {$supplier->name}...");

            try {
                $results = $scrapingService->scrapeSupplierPrices($supplier);
                $this->info("✓ {$supplier->name}: " . count($results) . " produits scrapés");
            } catch (\Exception $e) {
                $this->error("✗ Erreur pour {$supplier->name}: " . $e->getMessage());
            }
        }

        $this->info('Scraping terminé!');
        return 0;
    }
}
