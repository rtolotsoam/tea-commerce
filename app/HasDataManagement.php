<?php

namespace App;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;

trait HasDataManagement
{
    /**
     * Obtenir l'âge des données en heures
     */
    public function getAgeInHoursAttribute(): int
    {
        return $this->scraped_at->diffInHours(now());
    }

    /**
     * Obtenir l'âge des données en jours
     */
    public function getAgeInDaysAttribute(): int
    {
        return $this->scraped_at->diffInDays(now());
    }

    /**
     * Vérifier si les données sont récentes (moins de 24h)
     */
    public function getIsFreshAttribute(): bool
    {
        return $this->age_in_hours < 24;
    }

    /**
     * Vérifier si les données sont périmées (plus de 7 jours)
     */
    public function getIsStaleAttribute(): bool
    {
        return $this->age_in_days > 7;
    }

    /**
     * Scope pour les données récentes
     */
    public function scopeFresh(Builder $query): Builder
    {
        return $query->where('scraped_at', '>=', now()->subDay());
    }

    /**
     * Scope pour les données périmées
     */
    public function scopeStale(Builder $query): Builder
    {
        return $query->where('scraped_at', '<', now()->subWeek());
    }

    /**
     * Scope pour un fournisseur spécifique
     */
    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope pour un produit spécifique
     */
    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope pour un type de source
     */
    public function scopeBySourceType(Builder $query, string $type): Builder
    {
        return $query->where('source_type', $type);
    }

    /**
     * Obtenir les dernières données pour chaque produit d'un fournisseur
     */
    public static function getLatestForSupplier(int $supplierId)
    {
        return static::select('scraped_data.*')
            ->join(
                DB::raw('(SELECT product_id, MAX(scraped_at) as max_date
                          FROM scraped_data
                          WHERE supplier_id = ' . $supplierId . '
                          GROUP BY product_id) as latest'),
                function ($join) {
                    $join->on('scraped_data.product_id', '=', 'latest.product_id')
                         ->on('scraped_data.scraped_at', '=', 'latest.max_date');
                }
            )
            ->where('scraped_data.supplier_id', $supplierId)
            ->get();
    }

    /**
     * Extraire des données structurées du raw_data
     */
    public function extractFromRawData(string $key, $default = null)
    {
        return data_get($this->raw_data, $key, $default);
    }

    /**
     * Mettre à jour le produit associé si trouvé
     */
    public function linkToProduct(): bool
    {
        if ($this->product_id || !$this->supplier_ref) {
            return false;
        }

        $product = Product::where('supplier_id', $this->supplier_id)
            ->where('supplier_ref', $this->supplier_ref)
            ->first();

        if ($product) {
            $this->product_id = $product->id;
            return $this->save();
        }

        return false;
    }

    /**
     * Convertir en array pour l'historique
     */
    public function toHistoryArray(): array
    {
        return [
            'date' => $this->scraped_at->format('Y-m-d H:i'),
            'price' => $this->price,
            'currency' => $this->currency,
            'availability' => $this->availability,
            'stock_quantity' => $this->stock_quantity,
            'source' => $this->source_type,
            'url' => $this->source_url,
        ];
    }

    /**
     * Nettoyer les anciennes données
     */
    public static function cleanOldData(int $daysToKeep = 90): int
    {
        return static::where('scraped_at', '<', now()->subDays($daysToKeep))->delete();
    }
}
