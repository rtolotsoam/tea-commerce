<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShopifyPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'shopify_product_id',
        'shopify_variant_id',
        'selling_price',
        'compare_at_price',
        'currency',
        'last_sync_at',
    ];

    protected $casts = [
        'selling_price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'last_sync_at' => 'datetime',
    ];

    /**
     * Relation avec le produit
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Obtenir le prix de réduction
     */
    public function getDiscountAmountAttribute(): float
    {
        if ($this->compare_at_price && $this->compare_at_price > $this->selling_price) {
            return $this->compare_at_price - $this->selling_price;
        }
        return 0;
    }

    /**
     * Obtenir le pourcentage de réduction
     */
    public function getDiscountPercentAttribute(): float
    {
        if ($this->compare_at_price && $this->compare_at_price > 0) {
            return (($this->compare_at_price - $this->selling_price) / $this->compare_at_price) * 100;
        }
        return 0;
    }

    /**
     * Vérifier si le produit est en promotion
     */
    public function getIsOnSaleAttribute(): bool
    {
        return $this->compare_at_price && $this->compare_at_price > $this->selling_price;
    }

    /**
     * Vérifier si la synchronisation est nécessaire
     */
    public function getNeedsSyncAttribute(): bool
    {
        if (!$this->last_sync_at) {
            return true;
        }

        // Synchroniser si plus de 4 heures
        return $this->last_sync_at->diffInHours(now()) > 4;
    }

    /**
     * Mettre à jour depuis les données Shopify
     */
    public function updateFromShopify(array $shopifyData): void
    {
        $this->selling_price = $shopifyData['price'] ?? $this->selling_price;
        $this->compare_at_price = $shopifyData['compare_at_price'] ?? null;
        $this->currency = $shopifyData['currency'] ?? 'EUR';
        $this->last_sync_at = now();
        $this->save();

        // Recalculer la marge après mise à jour du prix
        if ($this->product->marginAnalysis) {
            $this->product->marginAnalysis->recalculate();
        }
    }

    /**
     * Scope pour les produits nécessitant une synchronisation
     */
    public function scopeNeedsSync($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('last_sync_at')
              ->orWhere('last_sync_at', '<', now()->subHours(4));
        });
    }

    /**
     * Scope pour les produits en promotion
     */
    public function scopeOnSale($query)
    {
        return $query->whereNotNull('compare_at_price')
                    ->whereRaw('compare_at_price > selling_price');
    }
}
