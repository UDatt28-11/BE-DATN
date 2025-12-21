<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SplitInvoice extends Model
{
    protected $fillable = [
        'original_invoice_id',
        'booking_detail_id',
        'new_invoice_id',
        'room_price',
        'service_price',
        'damage_price',
        'deposit_amount',
        'voucher_discount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'room_price' => 'decimal:2',
        'service_price' => 'decimal:2',
        'damage_price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'voucher_discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    // Relationships
    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'original_invoice_id');
    }

    public function newInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'new_invoice_id');
    }

    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class);
    }
}
