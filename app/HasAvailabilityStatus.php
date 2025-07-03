<?php

namespace App;

trait HasAvailabilityStatus
{
    /**
    * Statuts de disponibilité courants
    */
    public const AVAILABILITY_IN_STOCK = 'En stock';
    public const AVAILABILITY_OUT_OF_STOCK = 'Rupture de stock';
    public const AVAILABILITY_LIMITED = 'Stock limité';
    public const AVAILABILITY_PRE_ORDER = 'Précommande';
    public const AVAILABILITY_UNKNOWN = 'Inconnu';

    /**
     * Obtenir le statut de disponibilité normalisé
     */
    public function getNormalizedAvailabilityAttribute(): string
    {
        $availability = strtolower($this->availability ?? '');

        $checks = [
            [self::AVAILABILITY_OUT_OF_STOCK, ['rupture', 'épuisé', 'out of stock']],
            [self::AVAILABILITY_LIMITED, ['limité', 'faible', 'limited']],
            [self::AVAILABILITY_PRE_ORDER, ['précommande', 'pre-order']],
            [self::AVAILABILITY_IN_STOCK, ['stock'], ['rupture']], // avec exclusion
        ];

        return array_reduce($checks, function ($result, $check) use ($availability) {
            if ($result !== null) {
                return $result;
            }

            [$status, $keywords, $excludes] = array_pad($check, 3, []);

            $hasKeyword = collect($keywords)->some(fn ($keyword) => str_contains($availability, $keyword));
            $hasExclude = collect($excludes)->some(fn ($exclude) => str_contains($availability, $exclude));

            return $hasKeyword && !$hasExclude ? $status : null;
        }) ?? self::AVAILABILITY_UNKNOWN;
    }

    /**
     * Vérifier si le produit est disponible
     */
    public function getIsAvailableAttribute(): bool
    {
        return in_array($this->normalized_availability, [
            self::AVAILABILITY_IN_STOCK,
            self::AVAILABILITY_LIMITED,
            self::AVAILABILITY_PRE_ORDER,
        ]);
    }

    /**
     * Scope pour les produits disponibles
     */
    public function scopeAvailable($query)
    {
        return $query->whereIn('availability', [
            self::AVAILABILITY_IN_STOCK,
            self::AVAILABILITY_LIMITED,
        ]);
    }
}
