<?php

namespace App\Models;

use App\HasPriceAnalysis;
use App\HasDataManagement;
use App\HasAvailabilityStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScrapedData extends Model
{
    use HasFactory;
    use HasAvailabilityStatus;
    use HasPriceAnalysis;
    use HasDataManagement;

    /**
     * Nom de la table
     */
    protected $table = 'scraped_data';

    /**
     * Désactiver updated_at car on n'a que scraped_at
     */
    public $timestamps = false;

    /**
     * Les attributs mass assignable
     */
    protected $fillable = [
        'supplier_id',
        'product_id',
        'source_url',
        'source_type',
        'supplier_ref',
        'product_name',
        'price',
        'currency',
        'availability',
        'stock_quantity',
        'raw_data',
        'scraped_at',
    ];

    /**
     * Les attributs à caster
     */
    protected $casts = [
        'price' => 'decimal:4',
        'stock_quantity' => 'decimal:2',
        'raw_data' => 'array', // PostgreSQL JSONB
        'scraped_at' => 'datetime',
    ];

    /**
     * Valeurs par défaut
     */
    protected $attributes = [
        'currency' => 'EUR',
        'source_type' => 'scraping',
    ];

    /**
     * Types de sources possibles
     */
    public const SOURCE_TYPE_SCRAPING = 'scraping';
    public const SOURCE_TYPE_API = 'api';

    /**
     * Statuts de disponibilité courants
     */
    public const AVAILABILITY_IN_STOCK = 'En stock';
    public const AVAILABILITY_OUT_OF_STOCK = 'Rupture de stock';
    public const AVAILABILITY_LIMITED = 'Stock limité';
    public const AVAILABILITY_PRE_ORDER = 'Précommande';
    public const AVAILABILITY_UNKNOWN = 'Inconnu';

    /**
     * Relation avec le fournisseur
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Relation avec le produit (optionnelle)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Boot method pour définir scraped_at automatiquement
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->scraped_at) {
                $model->scraped_at = now();
            }
        });
    }
}
