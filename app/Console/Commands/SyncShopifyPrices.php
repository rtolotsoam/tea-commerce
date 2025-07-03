<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use App\Services\ShopifyService;

class SyncShopifyPrices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync-prices {--product= : SKU du produit}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchroniser les prix depuis Shopify';

    /**
     * Execute the console command.
     */
    public function handle(ShopifyService $shopifyService)
    {
        $productSku = $this->option('product');

        $query = Product::where('is_active', true)
            ->whereNotNull('shopify_product_id');

        if ($productSku) {
            $query->where('sku', $productSku);
        }

        $products = $query->get();

        if ($products->isEmpty()) {
            $this->error('Aucun produit à synchroniser');
            return 1;
        }

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $synced = 0;
        $errors = 0;

        foreach ($products as $product) {
            try {
                $shopifyService->syncProductPrice($product);
                $synced++;
            } catch (\Exception $e) {
                $this->error("\nErreur pour {$product->sku}: " . $e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        $this->info("Synchronisation terminée: {$synced} réussis, {$errors} erreurs");

        return $errors > 0 ? 1 : 0;
    }
}
