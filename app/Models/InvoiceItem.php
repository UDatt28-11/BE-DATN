<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'total_line',
        'item_type' // 'room_charge', 'service_charge', 'damage_fee', 'other'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_line' => 'decimal:2',
    ];

    // Relationships
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    // Scopes
    public function scopePenalties($query)
    {
        return $query->whereIn('item_type', ['damage_fee', 'other']);
    }

    public function scopeRegularItems($query)
    {
        return $query->whereIn('item_type', ['room_charge', 'service_charge']);
    }

    // Accessors & Mutators
    public function getFormattedUnitPriceAttribute()
    {
        return number_format($this->unit_price ?? 0, 2) . ' VNĐ';
    }

    public function getFormattedTotalLineAttribute()
    {
        return number_format($this->total_line ?? 0, 2) . ' VNĐ';
    }
}
