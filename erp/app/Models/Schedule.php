<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Schedule extends Model
{
    protected $fillable = [
        'tenant_id',
        'employee_id',
        'schedule_date',
        'shift_id',
        'is_day_off',
        'custom_start',
        'custom_end',
        'notes',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'is_day_off' => 'boolean',
        'custom_start' => 'datetime:H:i',
        'custom_end' => 'datetime:H:i',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
