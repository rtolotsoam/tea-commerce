<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'quantity_on_hand',
        'quantity_reserved',
        'reorder_point',
        'reorder_quantity',
        'location',
        'last_purchase_date',
        'last_purchase_price',
        'average_cost',
    ];

    protected $casts = [
        'quantity_on_hand' => 'decimal:2',
        'quantity_reserved' => 'decimal:2',
        'reorder_point' => 'decimal:2',
        'reorder_quantity' => 'decimal:2',
        'last_purchase_date' => 'date',
        'last_purchase_price' => 'decimal:4',
        'average_cost' => 'decimal:4',
    ];

    // Attribut calculé automatiquement par PostgreSQL
    protected $appends = ['quantity_available'];

    /**
     * Relation avec le produit
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relation avec les mouvements de stock
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'product_id', 'product_id');
    }

    /**
     * Obtenir la quantité disponible
     * (calculée automatiquement par PostgreSQL mais accessible comme attribut)
     */
    public function getQuantityAvailableAttribute(): float
    {
        return $this->attributes['quantity_available'] ??
               ($this->quantity_on_hand - $this->quantity_reserved);
    }

    /**
     * Vérifier si le stock est bas
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->quantity_available <= $this->reorder_point;
    }

    /**
     * Vérifier si le produit est en rupture de stock
     */
    public function getIsOutOfStockAttribute(): bool
    {
        return $this->quantity_available <= 0;
    }

    /**
     * Obtenir la valeur du stock
     */
    public function getStockValueAttribute(): float
    {
        return $this->quantity_on_hand * $this->average_cost;
    }

    /**
     * Réserver une quantité
     */
    public function reserve(float $quantity): bool
    {
        if ($quantity > $this->quantity_available) {
            return false;
        }

        $this->quantity_reserved += $quantity;
        return $this->save();
    }

    /**
     * Libérer une quantité réservée
     */
    public function release(float $quantity): bool
    {
        $this->quantity_reserved = max(0, $this->quantity_reserved - $quantity);
        return $this->save();
    }

    /**
     * Ajuster le stock
     */
    public function adjust(float $newQuantity, string $reason = 'manual adjustment'): void
    {
        $difference = $newQuantity - $this->quantity_on_hand;

        if ($difference != 0) {
            // Créer un mouvement de stock
            StockMovement::create([
                'product_id' => $this->product_id,
                'movement_type' => 'adjustment',
                'movement_reason' => $reason,
                'quantity' => abs($difference),
                'balance_before' => $this->quantity_on_hand,
                'balance_after' => $newQuantity,
            ]);

            $this->quantity_on_hand = $newQuantity;
            $this->save();
        }
    }

    /**
     * Mettre à jour le coût moyen
     */
    public function updateAverageCost(float $newPrice, float $quantity): void
    {
        if ($this->quantity_on_hand + $quantity <= 0) {
            $this->average_cost = $newPrice;
        } else {
            $totalValue = ($this->quantity_on_hand * $this->average_cost) + ($quantity * $newPrice);
            $totalQuantity = $this->quantity_on_hand + $quantity;
            $this->average_cost = $totalValue / $totalQuantity;
        }
    }

    /**
     * Scope pour les produits en rupture
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('quantity_available', '<=', 0);
    }

    /**
     * Scope pour les produits avec stock bas
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity_available <= reorder_point')
                    ->where('quantity_available', '>', 0);
    }
}
