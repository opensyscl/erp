<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Product extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'supplier_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'description',
        'price',
        'cost',
        'compare_price',
        'stock',
        'min_stock',
        'track_stock',
        'image',
        'images',
        'is_active',
        'is_archived',
        'is_offer',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'compare_price' => 'decimal:2',
        'images' => 'array',
        'track_stock' => 'boolean',
        'is_active' => 'boolean',
        'is_archived' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Check if stock is low (below min_stock threshold)
     */
    public function isLowStock(): bool
    {
        return $this->track_stock && $this->stock <= $this->min_stock && $this->stock > 0;
    }

    /**
     * Check if out of stock
     */
    public function isOutOfStock(): bool
    {
        return $this->track_stock && $this->stock <= 0;
    }

    /**
     * Get profit margin
     */
    public function getProfitMarginAttribute(): float
    {
        if ($this->cost <= 0) {
            return 0;
        }
        return (($this->price - $this->cost) / $this->cost) * 100;
    }

    /**
     * Get formatted price
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 0, ',', '.');
    }

    /**
     * Get formatted cost
     */
    public function getFormattedCostAttribute(): string
    {
        return '$' . number_format($this->cost, 0, ',', '.');
    }

    /**
     * Scope for active, non-archived products
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('is_archived', false);
    }

    /**
     * Scope for archived products
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Scope for low stock products
     */
    public function scopeLowStock($query)
    {
        return $query->where('track_stock', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('stock', '>', 0);
    }

    /**
     * Scope for out of stock products
     */
    public function scopeOutOfStock($query)
    {
        return $query->where('track_stock', true)->where('stock', '<=', 0);
    }

    /**
     * Scope for products without images
     */
    public function scopeWithoutImage($query)
    {
        return $query->whereNull('image');
    }

    /**
     * Scope for products without supplier
     */
    public function scopeWithoutSupplier($query)
    {
        return $query->whereNull('supplier_id');
    }
}
