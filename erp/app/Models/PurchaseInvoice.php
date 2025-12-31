<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseInvoice extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'supplier_id',
        'created_by',
        'invoice_number',
        'invoice_date',
        'received_date',
        'subtotal',
        'tax',
        'total_amount',
        'is_paid',
        'payment_date',
        'payment_due_date',
        'payment_method',
        'status',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'received_date' => 'date',
        'payment_date' => 'date',
        'payment_due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'is_paid' => 'boolean',
    ];

    /**
     * Get the supplier for this invoice.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the user who created this invoice.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the items in this invoice.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    /**
     * Scope for paid invoices.
     */
    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    /**
     * Scope for unpaid invoices.
     */
    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    /**
     * Scope for invoices from a specific supplier.
     */
    public function scopeFromSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Mark invoice as paid.
     */
    public function markAsPaid(?string $paymentMethod = null): void
    {
        $this->update([
            'is_paid' => true,
            'payment_date' => now()->toDateString(),
            'payment_method' => $paymentMethod,
            'status' => 'paid',
        ]);
    }
}
