<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'movement_type',
        'movement_reason',
        'reference_type',
        'reference_id',
        'quantity',
        'unit_cost',
        'balance_before',
        'balance_after',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:4',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public $timestamps = false;

    /**
     * Relation avec le produit
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relation polymorphique avec la référence
     */
    public function reference()
    {
        if ($this->reference_type === 'purchase') {
            return $this->belongsTo(Purchase::class, 'reference_id');
        }
        // Ajouter d'autres types si nécessaire
        return null;
    }

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($movement) {
            $movement->created_at = now();

            // Définir les balances si non définies
            if (is_null($movement->balance_before)) {
                $stock = Stock::where('product_id', $movement->product_id)->first();
                $movement->balance_before = $stock ? $stock->quantity_on_hand : 0;
            }

            if (is_null($movement->balance_after)) {
                $movement->balance_after = $movement->movement_type === 'in'
                    ? $movement->balance_before + $movement->quantity
                    : $movement->balance_before - $movement->quantity;
            }
        });
    }
}
