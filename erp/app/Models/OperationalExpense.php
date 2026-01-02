<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'date_paid',
        'expense_type',
        'description',
        'total_amount',
        'light',
        'water',
        'rent',
        'alarm',
        'internet',
        'iva',
        'repairs',
        'supplies',
        'other',
    ];

    protected $casts = [
        'date_paid' => 'date',
        'total_amount' => 'decimal:2',
        'light' => 'decimal:2',
        'water' => 'decimal:2',
        'rent' => 'decimal:2',
        'alarm' => 'decimal:2',
        'internet' => 'decimal:2',
        'iva' => 'decimal:2',
        'repairs' => 'decimal:2',
        'supplies' => 'decimal:2',
        'other' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
