<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MarginAnalysis extends Model
{
    use HasFactory;

    protected $table = 'margin_analysis';

    protected $fillable = [
        'product_id',
        'supplier_id',
        'purchase_price',
        'selling_price',
        'stock_quantity',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:4',
        'selling_price' => 'decimal:2',
        'stock_quantity' => 'decimal:2',
        'last_calculated_at' => 'datetime',
    ];

    // Colonnes calculées automatiquement par PostgreSQL
    protected $appends = ['margin_amount', 'margin_percent', 'potential_profit'];

    public $timestamps = false;

    /**
     * Relation avec le produit
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relation avec le fournisseur
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Obtenir le montant de la marge
     * (calculé automatiquement par PostgreSQL)
     */
    public function getMarginAmountAttribute(): float
    {
        return $this->attributes['margin_amount'] ??
               ($this->selling_price - $this->purchase_price);
    }

    /**
     * Obtenir le pourcentage de marge
     * (calculé automatiquement par PostgreSQL)
     */
    public function getMarginPercentAttribute(): float
    {
        return $this->attributes['margin_percent'] ??
               ($this->selling_price > 0 ?
                (($this->selling_price - $this->purchase_price) / $this->selling_price * 100) : 0);
    }

    /**
     * Obtenir le profit potentiel
     * (calculé automatiquement par PostgreSQL)
     */
    public function getPotentialProfitAttribute(): float
    {
        return $this->attributes['potential_profit'] ??
               ($this->margin_amount * $this->stock_quantity);
    }

    /**
     * Scope pour les marges élevées
     */
    public function scopeHighMargin($query, $minPercent = 50)
    {
        return $query->where('margin_percent', '>=', $minPercent);
    }

    /**
     * Scope pour les marges faibles
     */
    public function scopeLowMargin($query, $maxPercent = 20)
    {
        return $query->where('margin_percent', '<=', $maxPercent);
    }

    /**
     * Scope pour les produits rentables
     */
    public function scopeProfitable($query)
    {
        return $query->where('margin_amount', '>', 0)
                    ->where('stock_quantity', '>', 0);
    }

    /**
     * Recalculer la marge
     */
    public function recalculate(): void
    {
        $product = $this->product;

        if ($product->stock) {
            $this->purchase_price = $product->stock->average_cost ?? $product->stock->last_purchase_price ?? 0;
            $this->stock_quantity = $product->stock->quantity_available ?? 0;
        }

        if ($product->shopifyPrice) {
            $this->selling_price = $product->shopifyPrice->selling_price ?? 0;
        }

        $this->last_calculated_at = now();
        $this->save();
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->last_calculated_at = now();
        });
    }
}
