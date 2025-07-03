<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku', 'name', 'description', 'category_id', 'supplier_id',
        'supplier_ref', 'unit_weight', 'unit_type', 'min_order_quantity',
        'lead_time_days', 'is_active', 'shopify_product_id', 'shopify_variant_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'unit_weight' => 'decimal:3',
        'min_order_quantity' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stock(): HasOne
    {
        return $this->hasOne(Stock::class);
    }

    public function purchaseConditions(): HasMany
    {
        return $this->hasMany(PurchaseCondition::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function shopifyPrice(): HasOne
    {
        return $this->hasOne(ShopifyPrice::class);
    }

    public function marginAnalysis(): HasOne
    {
        return $this->hasOne(MarginAnalysis::class);
    }

    // Méthode pour obtenir le meilleur prix selon la quantité
    public function getBestPrice(float $quantity): ?PurchaseCondition
    {
        return $this->purchaseConditions()
            ->where('is_active', true)
            ->where('quantity_min', '<=', $quantity)
            ->where(function ($query) use ($quantity) {
                $query->whereNull('quantity_max')
                    ->orWhere('quantity_max', '>=', $quantity);
            })
            ->orderBy('unit_price', 'asc')
            ->first();
    }
}
