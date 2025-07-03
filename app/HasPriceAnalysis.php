<?php

namespace App;

trait HasPriceAnalysis
{
    /**
    * Obtenir le prix en euros (conversion si nécessaire)
    */
    public function getPriceInEurAttribute(): float
    {
        // Si déjà en EUR, retourner directement
        if ($this->currency === 'EUR') {
            return $this->price;
        }

        // Taux de conversion simples (à remplacer par un service de taux réel)
        $rates = [
            'USD' => 0.92,
            'GBP' => 1.16,
            'CHF' => 1.03,
            'CAD' => 0.68,
            'JPY' => 0.0061,
        ];

        $rate = $rates[$this->currency] ?? 1;
        return round($this->price * $rate, 4);
    }

    /**
     * Comparer avec le prix actuel du produit
     */
    public function getPriceDifferenceAttribute(): ?float
    {
        if (!$this->product_id || !$this->product) {
            return null;
        }

        $currentPrice = $this->product->purchaseConditions()
            ->active()
            ->orderBy('unit_price')
            ->value('unit_price');

        return $currentPrice ? $this->price - $currentPrice : null;
    }

    /**
     * Obtenir le pourcentage de variation du prix
     */
    public function getPriceVariationPercentAttribute(): ?float
    {
        if (!$this->price_difference || !$this->product) {
            return null;
        }

        $currentPrice = $this->product->purchaseConditions()
            ->active()
            ->orderBy('unit_price')
            ->value('unit_price');

        return $currentPrice > 0 ? ($this->price_difference / $currentPrice * 100) : null;
    }

    /**
     * Créer une alerte si variation de prix importante
     */
    public function checkPriceAlert(float $threshold = 10): bool
    {
        if (!$this->price_variation_percent) {
            return false;
        }

        return abs($this->price_variation_percent) >= $threshold;
    }

    /**
     * Obtenir l'historique des prix pour un produit
     */
    public static function getPriceHistory(int $productId, int $days = 30)
    {
        return static::where('product_id', $productId)
            ->where('scraped_at', '>=', now()->subDays($days))
            ->orderBy('scraped_at')
            ->get()
            ->map(function ($data) {
                return [
                    'date' => $data->scraped_at->format('Y-m-d'),
                    'price' => $data->price_in_eur,
                    'availability' => $data->normalized_availability,
                ];
            });
    }
}
