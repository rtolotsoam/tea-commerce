<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Models\Supplier;
use App\Models\ScrapedData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class ScrapingService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
    }

    /**
     * Scraper les prix d'un fournisseur
     */
    public function scrapeSupplierPrices(Supplier $supplier): array
    {
        $results = [];

        // Vérifier si le fournisseur a une URL
        if (!$supplier->website_url) {
            Log::warning("Pas d'URL pour le fournisseur {$supplier->name}");
            return $results;
        }

        try {
            // Utiliser la configuration spécifique du fournisseur si elle existe
            $config = $this->getSupplierConfig($supplier->code);

            $response = $this->client->get($supplier->website_url);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);

            // Utiliser les sélecteurs spécifiques au fournisseur
            $productSelector = $config['selectors']['product'] ?? '.product-item';
            $linkSelector = $config['selectors']['link'] ?? 'a';
            $refSelector = $config['selectors']['ref'] ?? '.product-ref';
            $nameSelector = $config['selectors']['name'] ?? '.product-name';
            $priceSelector = $config['selectors']['price'] ?? '.product-price';
            $availabilitySelector = $config['selectors']['availability'] ?? '.availability';

            $crawler->filter($productSelector)->each(function (Crawler $node) use (
                $supplier,
                &$results,
                $linkSelector,
                $refSelector,
                $nameSelector,
                $priceSelector,
                $availabilitySelector,
                $productSelector
            ) {
                try {
                    $data = [
                        'supplier_id' => $supplier->id,
                        'source_url' => $this->extractUrl($node, $linkSelector, $supplier->website_url),
                        'source_type' => 'scraping',
                        'supplier_ref' => $this->extractText($node, $refSelector),
                        'product_name' => $this->extractText($node, $nameSelector),
                        'price' => $this->extractPrice($this->extractText($node, $priceSelector)),
                        'currency' => 'EUR',
                        'availability' => $this->extractText($node, $availabilitySelector, 'Unknown'),
                        'stock_quantity' => $this->extractStockQuantity($node, $availabilitySelector),
                        'raw_data' => json_encode([
                            'html' => $node->html(),
                            'scraped_at' => now()->toIso8601String(),
                            'selectors_used' => [
                                'product' => $productSelector,
                                'price' => $priceSelector,
                            ]
                        ])
                    ];

                    // Créer uniquement si on a au moins un nom et un prix
                    if (!empty($data['product_name']) && $data['price'] > 0) {
                        $scraped = ScrapedData::create($data);

                        // Essayer de lier au produit local
                        $scraped->linkToProduct();

                        $results[] = $data;
                    }
                } catch (\Exception $e) {
                    Log::warning("Erreur extraction produit: " . $e->getMessage());
                }
            });

            Log::info("Scraping terminé pour {$supplier->name}: " . count($results) . " produits trouvés");

        } catch (\Exception $e) {
            Log::error("Erreur scraping {$supplier->name}: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Scraper tous les fournisseurs actifs
     */
    public function scrapeAllSuppliers(): array
    {
        $results = [
            'total_suppliers' => 0,
            'successful' => 0,
            'failed' => 0,
            'total_products' => 0,
            'details' => []
        ];

        // Récupérer tous les fournisseurs actifs
        $suppliers = Supplier::where('is_active', true)
            ->whereNotNull('website_url')
            ->get();

        $results['total_suppliers'] = $suppliers->count();

        foreach ($suppliers as $supplier) {
            try {
                Log::info("Début scraping pour {$supplier->name}");

                // Vérifier le rate limiting par fournisseur
                $cacheKey = "last_scrape_{$supplier->id}";
                $lastScrape = Cache::get($cacheKey);

                if ($lastScrape && now()->diffInMinutes($lastScrape) < 60) {
                    Log::info("Scraping ignoré pour {$supplier->name} - trop récent");
                    $results['details'][] = [
                        'supplier' => $supplier->name,
                        'status' => 'skipped',
                        'reason' => 'Rate limit',
                        'products' => 0
                    ];
                    continue;
                }

                // Scraper le fournisseur
                $supplierResults = $this->scrapeSupplierPrices($supplier);

                $results['successful']++;
                $results['total_products'] += count($supplierResults);
                $results['details'][] = [
                    'supplier' => $supplier->name,
                    'status' => 'success',
                    'products' => count($supplierResults)
                ];

                // Mettre à jour le cache
                Cache::put($cacheKey, now(), 3600);

                // Pause entre les fournisseurs pour éviter d'être bloqué
                if ($suppliers->count() > 1) {
                    sleep(rand(2, 5));
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'supplier' => $supplier->name,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'products' => 0
                ];

                Log::error("Erreur scraping {$supplier->name}: " . $e->getMessage());
            }
        }

        // Nettoyer les anciennes données après le scraping
        if ($results['successful'] > 0) {
            $this->cleanupOldData();
        }

        return $results;
    }

    /**
     * Extraire le prix d'un texte
     */
    private function extractPrice(string $priceText): float
    {
        // Nettoyer et extraire le prix
        $price = preg_replace('/[^0-9,.]/', '', $priceText);
        $price = str_replace(',', '.', $price);
        return floatval($price);
    }

    /**
     * Extraire la quantité en stock d'un texte
     */
    private function extractStockQuantity(Crawler $node, string $selector): ?float
    {
        try {
            $text = $this->extractText($node, $selector);

            // Chercher des patterns de quantité
            if (preg_match('/(\d+)\s*(pièces?|unités?|en stock)/i', $text, $matches)) {
                return floatval($matches[1]);
            }

            // Si "En stock" sans quantité, retourner null
            if (stripos($text, 'en stock') !== false) {
                return null;
            }

            // Si "Rupture", retourner 0
            if (stripos($text, 'rupture') !== false || stripos($text, 'épuisé') !== false) {
                return 0;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extraire du texte d'un noeud
     */
    private function extractText(Crawler $node, string $selector, string $default = ''): string
    {
        try {
            if ($node->filter($selector)->count() > 0) {
                return trim($node->filter($selector)->first()->text());
            }
        } catch (\Exception $e) {
            // Ignorer l'erreur et retourner la valeur par défaut
        }

        return $default;
    }

    /**
     * Extraire une URL
     */
    private function extractUrl(Crawler $node, string $selector, string $baseUrl): string
    {
        try {
            if ($node->filter($selector)->count() > 0) {
                $url = $node->filter($selector)->first()->attr('href');

                // Convertir en URL absolue si nécessaire
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $parsedBase = parse_url($baseUrl);
                    $baseScheme = $parsedBase['scheme'] ?? 'https';
                    $baseHost = $parsedBase['host'] ?? '';

                    if (strpos($url, '/') === 0) {
                        // URL relative
                        $url = $baseScheme . '://' . $baseHost . $url;
                    } else {
                        // URL relative au dossier
                        $url = rtrim($baseUrl, '/') . '/' . $url;
                    }
                }

                return $url;
            }
        } catch (\Exception $e) {
            // Ignorer l'erreur
        }

        return '';
    }

    /**
     * Obtenir la configuration spécifique d'un fournisseur
     */
    private function getSupplierConfig(string $supplierCode): array
    {
        // Configuration spécifique par fournisseur
        $configs = [
            'SUPP001' => [
                'selectors' => [
                    'product' => '.product-item',
                    'link' => 'a.product-link',
                    'ref' => '.product-sku',
                    'name' => '.product-title',
                    'price' => '.price-now',
                    'availability' => '.stock-status'
                ]
            ],
            'SUPP002' => [
                'selectors' => [
                    'product' => 'div.tea-product',
                    'link' => 'h3 a',
                    'ref' => '.reference',
                    'name' => 'h3.title',
                    'price' => 'span.price',
                    'availability' => '.availability'
                ]
            ],
            // Ajouter d'autres configurations selon les fournisseurs
        ];

        return $configs[$supplierCode] ?? [
            'selectors' => [
                'product' => '.product-item',
                'link' => 'a',
                'ref' => '.product-ref',
                'name' => '.product-name',
                'price' => '.product-price',
                'availability' => '.availability'
            ]
        ];
    }

    /**
     * Nettoyer les anciennes données
     */
    private function cleanupOldData(): void
    {
        try {
            $daysToKeep = config('scraping.days_to_keep', 90);
            $deleted = ScrapedData::where('scraped_at', '<', now()->subDays($daysToKeep))->delete();

            if ($deleted > 0) {
                Log::info("Nettoyage: {$deleted} anciennes données supprimées");
            }
        } catch (\Exception $e) {
            Log::error("Erreur nettoyage données: " . $e->getMessage());
        }
    }
}
