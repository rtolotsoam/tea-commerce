<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'product_id',
        'quantity_min',
        'quantity_max',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected $casts = [
        'quantity_min' => 'decimal:2',
        'quantity_max' => 'decimal:2',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Relation avec le fournisseur
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relation avec le produit
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Vérifier si la condition est valide à une date donnée
     * Version améliorée avec moins de returns
     */
    public function isValidAt($date = null): bool
    {
        // Conversion de la date
        $checkDate = $date ? Carbon::parse($date) : now();

        // Vérification combinée de toutes les conditions
        return $this->is_active
            && (!$this->valid_from || $checkDate->gte($this->valid_from))
            && (!$this->valid_until || $checkDate->lte($this->valid_until));
    }

    /**
     * Vérifier si une quantité est dans la plage
     */
    public function isQuantityInRange(float $quantity): bool
    {
        return $quantity >= $this->quantity_min
            && (!$this->quantity_max || $quantity <= $this->quantity_max);
    }

    /**
     * Calculer le prix net après remise pour une quantité donnée
     */
    public function getNetPrice(float $quantity = 1): float
    {
        $totalPrice = $this->unit_price * $quantity;

        // Appliquer la remise en pourcentage
        if ($this->discount_percent > 0) {
            $totalPrice *= (1 - $this->discount_percent / 100);
        }

        // Appliquer la remise en montant fixe
        if ($this->discount_amount > 0) {
            $totalPrice -= $this->discount_amount;
        }

        // S'assurer que le prix ne soit pas négatif et retourner le prix unitaire
        return $quantity > 0 ? max(0, $totalPrice / $quantity) : 0;
    }

    /**
     * Obtenir le prix unitaire net (attribut accessor)
     */
    public function getNetUnitPriceAttribute(): float
    {
        return $this->getNetPrice(1);
    }

    /**
     * Obtenir le montant total de remise pour une quantité
     */
    public function getTotalDiscountAmount(float $quantity = 1): float
    {
        $originalTotal = $this->unit_price * $quantity;
        $netTotal = $this->getNetPrice($quantity) * $quantity;

        return max(0, $originalTotal - $netTotal);
    }

    /**
     * Obtenir le pourcentage de remise effectif
     */
    public function getEffectiveDiscountPercentAttribute(): float
    {
        if ($this->unit_price <= 0) {
            return 0;
        }

        $discount = $this->unit_price - $this->net_unit_price;
        return ($discount / $this->unit_price) * 100;
    }

    /**
     * Vérifier si cette condition est meilleure qu'une autre pour une quantité donnée
     */
    public function isBetterThan(PurchaseCondition $other, float $quantity): bool
    {
        // Vérifier que les deux conditions sont valides pour la quantité
        if (!$this->isQuantityInRange($quantity) || !$other->isQuantityInRange($quantity)) {
            return $this->isQuantityInRange($quantity);
        }

        return $this->getNetPrice($quantity) < $other->getNetPrice($quantity);
    }

    /**
     * Scope pour les conditions actives et valides
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
                    ->where(function ($q) {
                        $q->whereNull('valid_from')
                          ->orWhere('valid_from', '<=', now());
                    })
                    ->where(function ($q) {
                        $q->whereNull('valid_until')
                          ->orWhere('valid_until', '>=', now());
                    });
    }

    /**
     * Scope pour une quantité donnée
     */
    public function scopeForQuantity($query, float $quantity)
    {
        return $query->where('quantity_min', '<=', $quantity)
                    ->where(function ($q) use ($quantity) {
                        $q->whereNull('quantity_max')
                          ->orWhere('quantity_max', '>=', $quantity);
                    });
    }

    /**
     * Scope pour un produit spécifique
     */
    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope pour un fournisseur spécifique
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Obtenir la meilleure condition pour un produit et une quantité
     */
    public static function getBestForProductQuantity(int $productId, float $quantity, $date = null)
    {
        $conditions = static::forProduct($productId)
            ->active()
            ->forQuantity($quantity)
            ->get();

        // Filtrer par date si fournie
        if ($date) {
            $conditions = $conditions->filter(function ($condition) use ($date) {
                return $condition->isValidAt($date);
            });
        }

        // Retourner la condition avec le meilleur prix net
        return $conditions->sortBy(function ($condition) use ($quantity) {
            return $condition->getNetPrice($quantity);
        })->first();
    }

    /**
     * Obtenir toutes les conditions groupées par plages de quantité
     */
    public static function getQuantityBreaksForProduct(int $productId)
    {
        return static::forProduct($productId)
            ->active()
            ->orderBy('quantity_min')
            ->get()
            ->map(function ($condition) {
                return [
                    'quantity_min' => $condition->quantity_min,
                    'quantity_max' => $condition->quantity_max,
                    'unit_price' => $condition->unit_price,
                    'net_price' => $condition->net_unit_price,
                    'discount_percent' => $condition->effective_discount_percent,
                    'label' => $condition->getQuantityRangeLabel(),
                ];
            });
    }

    /**
     * Obtenir un label pour la plage de quantité
     */
    public function getQuantityRangeLabel(): string
    {
        if ($this->quantity_max) {
            return sprintf('%d - %d', $this->quantity_min, $this->quantity_max);
        }

        return sprintf('%d+', $this->quantity_min);
    }

    /**
     * Vérifier si la condition expire bientôt (dans les 30 jours)
     */
    public function getExpiresAttribute(): bool
    {
        return $this->valid_until
            && $this->valid_until->between(now(), now()->addDays(30));
    }

    /**
     * Obtenir le nombre de jours avant expiration
     */
    public function getDaysUntilExpirationAttribute(): ?int
    {
        if (!$this->valid_until) {
            return null;
        }

        $days = now()->diffInDays($this->valid_until, false);
        return $days >= 0 ? $days : null;
    }

    /**
     * Dupliquer la condition pour une nouvelle période
     */
    public function duplicateForPeriod(Carbon $validFrom, Carbon $validUntil): self
    {
        return static::create([
            'supplier_id' => $this->supplier_id,
            'product_id' => $this->product_id,
            'quantity_min' => $this->quantity_min,
            'quantity_max' => $this->quantity_max,
            'unit_price' => $this->unit_price,
            'discount_percent' => $this->discount_percent,
            'discount_amount' => $this->discount_amount,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'is_active' => true,
        ]);
    }

    /**
     * Convertir en array pour l'export
     */
    public function toExportArray(): array
    {
        return [
            'supplier_code' => $this->supplier->code,
            'product_sku' => $this->product->sku,
            'quantity_min' => $this->quantity_min,
            'quantity_max' => $this->quantity_max ?? '',
            'unit_price' => number_format($this->unit_price, 2),
            'discount_percent' => $this->discount_percent,
            'discount_amount' => $this->discount_amount ?? 0,
            'net_price' => number_format($this->net_unit_price, 2),
            'valid_from' => $this->valid_from?->format('Y-m-d') ?? '',
            'valid_until' => $this->valid_until?->format('Y-m-d') ?? '',
            'active' => $this->is_active ? 'Oui' : 'Non',
        ];
    }
}
