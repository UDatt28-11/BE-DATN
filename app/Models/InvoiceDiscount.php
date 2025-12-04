<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceDiscount extends Model
{
    protected $table = 'invoice_discounts';

    protected $fillable = [
        'invoice_id',
        'discount_type',
        'discount_value',
        'discount_amount',
        'reason',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the invoice this discount belongs to
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the user who approved this discount
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Check if discount is approved
     */
    public function isApproved(): bool
    {
        return !is_null($this->approved_at);
    }
}
