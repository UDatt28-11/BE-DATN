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
        'tax_rate',
        'tax_amount',
        'amount',
        'total_line',
        'total',
        'item_type', // 'room_charge', 'service_charge', 'penalty', 'other'
        'booking_detail_id',
        'room_id',
        'service_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'total_line' => 'decimal:2',
        'total' => 'decimal:2',
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
