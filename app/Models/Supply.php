<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supply extends Model
{
    protected $fillable = [
        'room_id',
        'name',
        'description',
        'category',
        'unit',
        'current_stock',
        'min_stock_level',
        'max_stock_level',
        'unit_price',
        'supplier',
        'supplier_contact',
        'status' // 'active', 'inactive', 'discontinued'
    ];

    protected $casts = [
        'current_stock' => 'integer',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    // Relationships
    public function supplyLogs(): HasMany
    {
        return $this->hasMany(SupplyLog::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('current_stock <= min_stock_level');
    }

    public function scopeOutOfStock($query)
    {
        return $query->where('current_stock', 0);
    }

    // Accessors & Mutators
    public function getStockStatusAttribute()
    {
        if ($this->current_stock == 0) {
            return 'out_of_stock';
        } elseif ($this->current_stock <= $this->min_stock_level) {
            return 'low_stock';
        } else {
            return 'in_stock';
        }
    }

    public function getFormattedUnitPriceAttribute()
    {
        return number_format((float) $this->unit_price, 2) . ' VNĐ';
    }

    public function getTotalValueAttribute()
    {
        return $this->current_stock * (float) $this->unit_price;
    }

    public function getFormattedTotalValueAttribute()
    {
        return number_format((float) $this->total_value, 2) . ' VNĐ';
    }
}
