<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Invoice extends Model
{
    protected $fillable = [
        'booking_order_id',
        'issue_date',
        'due_date',
        'total_amount',
        'status',
        'discount_amount',
        'refund_amount',
        'refund_policy_id',
        'refund_date',
        'calculation_method',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'refund_date' => 'date',
    ];

    // Relationships
    public function bookingOrder(): BelongsTo
    {
        return $this->belongsTo(BookingOrder::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(InvoiceDiscount::class);
    }

    public function refundPolicy(): BelongsTo
    {
        return $this->belongsTo(RefundPolicy::class);
    }

    public function promotions(): BelongsToMany
    {
        return $this->belongsToMany(Promotion::class, 'promotion_usage', 'booking_order_id', 'promotion_id')
            ->withTimestamps()
            ->withPivot('applied_discount_amount');
    }

    // Scopes
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeUnpaid($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'overdue');
    }

    // Helper Methods
    /**
     * Get net amount after discount
     */
    public function getNetAmountAttribute()
    {
        return $this->total_amount - $this->discount_amount;
    }

    /**
     * Get total discounts (all discounts on this invoice)
     */
    public function getTotalDiscounts()
    {
        return $this->discounts()->sum('discount_amount');
    }

    /**
     * Get all discounts including from items
     */
    public function getAllDiscountsAmount()
    {
        $itemDiscounts = $this->invoiceItems()->sum('discount_amount');
        return $this->discount_amount + $itemDiscounts;
    }

    /**
     * Calculate amount after refund
     */
    public function getAmountAfterRefund()
    {
        return $this->total_amount - $this->refund_amount;
    }
}
