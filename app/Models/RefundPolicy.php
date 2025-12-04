<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefundPolicy extends Model
{
    protected $table = 'refund_policies';

    protected $fillable = [
        'name',
        'refund_percent',
        'days_before_checkin',
        'penalty_percent',
        'is_active',
    ];

    protected $casts = [
        'refund_percent' => 'decimal:2',
        'penalty_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get invoices that use this refund policy
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Scope for active policies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
