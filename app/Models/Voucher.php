<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Voucher extends Model
{
    protected $fillable = [
        'property_id',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_count',
        'max_usage_per_user',
        'start_date',
        'end_date',
        'is_active',
        'is_public',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_count' => 'integer',
        'max_usage_per_user' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    // Relationships
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_vouchers', 'voucher_id', 'user_id')
            ->withPivot('booking_order_id', 'claimed_at', 'used_at')
            ->withTimestamps();
    }

    public function bookingOrders(): BelongsToMany
    {
        return $this->belongsToMany(BookingOrder::class, 'user_vouchers', 'voucher_id', 'booking_order_id')
            ->withPivot('user_id', 'claimed_at', 'used_at')
            ->withTimestamps();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeAvailable($query)
    {
        return $query->active()
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                  ->orWhereColumn('usage_count', '<', 'usage_limit');
            });
    }

    // Methods
    
    /**
     * Tính số tiền giảm giá dựa trên tổng đơn hàng
     */
    public function calculateDiscount(float $orderAmount): float
    {
        // Kiểm tra đơn tối thiểu
        if ($orderAmount < $this->min_order_amount) {
            return 0;
        }

        $discount = 0;

        if ($this->discount_type === 'percentage') {
            $discount = ($orderAmount * $this->discount_value) / 100;
            
            // Áp dụng giới hạn giảm tối đa
            if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
                $discount = $this->max_discount_amount;
            }
        } else {
            // fixed_amount
            $discount = $this->discount_value;
        }

        // Không giảm quá tổng đơn
        return min($discount, $orderAmount);
    }

    /**
     * Kiểm tra voucher có thể sử dụng được không
     */
    public function canBeUsed(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->start_date && $this->start_date->isFuture()) {
            return false;
        }

        if ($this->end_date && $this->end_date->isPast()) {
            return false;
        }

        if ($this->usage_limit !== null && $this->usage_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    /**
     * Kiểm tra user có thể sử dụng voucher này không
     */
    public function canBeUsedByUser(int $userId): bool
    {
        if (!$this->canBeUsed()) {
            return false;
        }

        // Đếm số lần user đã dùng voucher này
        $userUsageCount = UserVoucher::where('voucher_id', $this->id)
            ->where('user_id', $userId)
            ->whereNotNull('used_at')
            ->count();

        return $userUsageCount < $this->max_usage_per_user;
    }

    /**
     * Kiểm tra user đã claim voucher này chưa
     */
    public function isClaimedByUser(int $userId): bool
    {
        return UserVoucher::where('voucher_id', $this->id)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Get formatted discount text
     */
    public function getDiscountTextAttribute(): string
    {
        if ($this->discount_type === 'percentage') {
            $text = 'Giảm ' . intval($this->discount_value) . '%';
            if ($this->max_discount_amount) {
                $text .= ' (tối đa ' . number_format($this->max_discount_amount, 0, ',', '.') . 'đ)';
            }
            return $text;
        }

        return 'Giảm ' . number_format($this->discount_value, 0, ',', '.') . 'đ';
    }
}

