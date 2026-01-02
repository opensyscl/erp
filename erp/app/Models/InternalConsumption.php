<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InternalConsumption extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'quantity_removed',
        'cost_price_at_time',
        'sale_price_at_time',
        'notes',
        'removal_date',
    ];

    protected $casts = [
        'removal_date' => 'datetime',
        'quantity_removed' => 'integer',
        'cost_price_at_time' => 'decimal:2',
        'sale_price_at_time' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
