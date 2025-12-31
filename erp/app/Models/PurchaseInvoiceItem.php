<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_invoice_id',
        'product_id',
        'quantity',
        'previous_cost',
        'new_cost',
        'margin_percentage',
        'calculated_sale_price',
        'subtotal',
        'update_product_cost',
        'update_product_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'previous_cost' => 'decimal:2',
        'new_cost' => 'decimal:2',
        'margin_percentage' => 'decimal:2',
        'calculated_sale_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'update_product_cost' => 'boolean',
        'update_product_price' => 'boolean',
    ];

    /**
     * Get the purchase invoice this item belongs to.
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class);
    }

    /**
     * Get the product for this item.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Calculate sale price based on margin.
     */
    public function calculateSalePrice(float $marginPercentage): float
    {
        $cost = (float) $this->new_cost;
        return round($cost * (1 + ($marginPercentage / 100)), 2);
    }

    /**
     * Apply cost and price updates to the product.
     */
    public function applyToProduct(): void
    {
        $product = $this->product;

        if ($this->update_product_cost) {
            $product->cost = $this->new_cost;
        }

        if ($this->update_product_price && $this->calculated_sale_price) {
            $product->price = $this->calculated_sale_price;
        }

        // Add stock
        $product->stock += (float) $this->quantity;

        $product->save();
    }
}
