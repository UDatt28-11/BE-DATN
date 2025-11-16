<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Invoice extends Model
{
    protected $fillable = [
        'property_id',
        'booking_order_id',
        'invoice_number',
        'issue_date',
        'due_date',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_address',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'paid_amount',
        'balance',
        'status', // Giữ lại để tương thích
        'payment_status',
        'invoice_status',
        'payment_method',
        'payment_date',
        'payment_notes',
        'notes',
        'terms_conditions',
        'refund_amount',
        'refund_policy_id',
        'refund_date',
        'calculation_method',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'issue_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
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
