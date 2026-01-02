<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CashClosing extends Model
{
    use HasFactory;

    protected $fillable = [
        'closing_date',
        'starting_cash',
        'ending_cash',
        'pos1_sales',
        'pos2_sales',
        'total_day_cash',
        'deposit_meli',
        'deposit_bchile',
        'deposit_bsantander',
        'other_outgoings',
        'total_outgoings',
        'total_day_income',
        'income_plus_outgoings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'closing_date' => 'date',
        'starting_cash' => 'decimal:0',
        'ending_cash' => 'decimal:0',
        'pos1_sales' => 'decimal:0',
        'pos2_sales' => 'decimal:0',
        'total_day_cash' => 'decimal:0',
        'deposit_meli' => 'decimal:0',
        'deposit_bchile' => 'decimal:0',
        'deposit_bsantander' => 'decimal:0',
        'other_outgoings' => 'decimal:0',
        'total_outgoings' => 'decimal:0',
        'total_day_income' => 'decimal:0',
        'income_plus_outgoings' => 'decimal:0',
    ];
}
