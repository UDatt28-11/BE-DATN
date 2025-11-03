<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplyLog extends Model
{
    protected $fillable = [
        'supply_id',
        'user_id',
        'change_quantity',
        'reason'
    ];

    protected $casts = [
        'change_quantity' => 'integer',
        'user_id' => 'integer',
    ];

    // Relationships
    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeInbound($query)
    {
        return $query->where('action_type', 'in');
    }

    public function scopeOutbound($query)
    {
        return $query->where('action_type', 'out');
    }

    public function scopeAdjustments($query)
    {
        return $query->where('action_type', 'adjustment');
    }

    public function scopeTransfers($query)
    {
        return $query->where('action_type', 'transfer');
    }

    // Accessors
    public function getActionTypeLabelAttribute()
    {
        return match($this->action_type) {
            'in' => 'Nhập kho',
            'out' => 'Xuất kho',
            'adjustment' => 'Điều chỉnh',
            'transfer' => 'Chuyển kho',
            default => 'Khác'
        };
    }

    public function getStockChangeAttribute()
    {
        return $this->new_stock - $this->previous_stock;
    }
}
