<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'quantity',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_rate',
        'subtotal',
        'total',
        'received_quantity',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
        'received_quantity' => 'decimal:2',
    ];

    /**
     * Relation avec la commande
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * Relation avec le produit
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculer le montant total de la ligne
     */
    public function calculateTotals(): void
    {
        // Calcul du sous-total
        $baseAmount = $this->quantity * $this->unit_price;

        // Appliquer la remise
        if ($this->discount_percent > 0) {
            $this->discount_amount = $baseAmount * ($this->discount_percent / 100);
        }

        $this->subtotal = $baseAmount - $this->discount_amount;

        // Appliquer la TVA
        $taxAmount = $this->subtotal * ($this->tax_rate / 100);
        $this->total = $this->subtotal + $taxAmount;
    }

    /**
     * Obtenir le prix unitaire net (après remise)
     */
    public function getNetUnitPriceAttribute(): float
    {
        if ($this->quantity > 0) {
            return $this->subtotal / $this->quantity;
        }
        return 0;
    }

    /**
     * Obtenir le montant de TVA
     */
    public function getTaxAmountAttribute(): float
    {
        return $this->subtotal * ($this->tax_rate / 100);
    }

    /**
     * Vérifier si la ligne est complètement reçue
     */
    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->received_quantity >= $this->quantity;
    }

    /**
     * Obtenir la quantité restante à recevoir
     */
    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    /**
     * Boot method pour calculer automatiquement les totaux
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->calculateTotals();
        });
    }
}
