<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'customer_id',
        'subtotal',
        'tax',
        'discount',
        'total',
        'paid',
        'change',
        'receipt_number',
        'payment_method',
        'voucher',
        'cost_of_goods_sold',
        'status',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid' => 'decimal:2',
        'change' => 'decimal:2',
        'cost_of_goods_sold' => 'decimal:2',
    ];

    /**
     * Get the user (cashier) who made the sale.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the items in this sale.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Scope for completed sales.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for sales within a date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Calculate profit from this sale.
     */
    public function getProfitAttribute(): float
    {
        return (float) $this->total - (float) $this->cost_of_goods_sold;
    }

    /**
     * Get formatted receipt number.
     */
    public function getFormattedReceiptNumberAttribute(): string
    {
        return sprintf('#%06d', $this->receipt_number);
    }
}
