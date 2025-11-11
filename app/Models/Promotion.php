<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Promotion extends Model
{
    protected $fillable = [
        'property_id',
        'code',
        'description',
        'discount_type', // percentage, fixed_amount
        'discount_value',
        'max_discount_amount', // Giới hạn giảm tối đa (cho percentage)
        'min_purchase_amount', // Giá trị đơn hàng tối thiểu
        'max_usage_limit', // Số lần sử dụng tối đa
        'max_usage_per_user', // Số lần mỗi user có thể dùng
        'usage_count',
        'start_date',
        'end_date',
        'is_active',
        'applicable_to', // all, specific_rooms, specific_room_types
        'additional_settings',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_purchase_amount' => 'decimal:2',
        'usage_count' => 'integer',
        'max_usage_limit' => 'integer',
        'max_usage_per_user' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'additional_settings' => 'array',
    ];

    // Relationships
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'promotion_room', 'promotion_id', 'room_id');
    }

    public function roomTypes(): BelongsToMany
    {
        return $this->belongsToMany(RoomType::class, 'promotion_room_type', 'promotion_id', 'room_type_id');
    }

    public function bookingOrders(): BelongsToMany
    {
        return $this->belongsToMany(BookingOrder::class, 'promotion_usage', 'promotion_id', 'booking_order_id')
            ->withTimestamps()
            ->withPivot('applied_discount_amount');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', 1)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public function scopeByProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    // Methods
    /**
     * Check if promotion is valid for use
     */
    public function isValid(): bool
    {
        return $this->is_active
            && $this->start_date <= now()
            && $this->end_date >= now()
            && ($this->max_usage_limit === null || $this->usage_count < $this->max_usage_limit);
    }

    /**
     * Calculate discount amount based on total
     */
    public function calculateDiscount($totalAmount): float
    {
        // Check minimum purchase amount
        if ($this->min_purchase_amount && $totalAmount < $this->min_purchase_amount) {
            return 0;
        }

        $discountAmount = 0;

        if ($this->discount_type === 'percentage') {
            $discountAmount = ($totalAmount * $this->discount_value) / 100;

            // Apply max discount limit if set
            if ($this->max_discount_amount) {
                $discountAmount = min($discountAmount, $this->max_discount_amount);
            }
        } else if ($this->discount_type === 'fixed_amount') {
            $discountAmount = $this->discount_value;
        }

        return min($discountAmount, $totalAmount);
    }

    /**
     * Apply promotion to booking
     */
    public function applyPromotion($bookingOrderId, $appliedAmount = null)
    {
        // Check usage limit per user
        if ($this->max_usage_per_user) {
            $bookingOrder = BookingOrder::find($bookingOrderId);
            $userUsageCount = $this->bookingOrders()
                ->where('user_id', $bookingOrder->user_id)
                ->count();

            if ($userUsageCount >= $this->max_usage_per_user) {
                throw new \Exception('Mã giảm giá đã được sử dụng số lần tối đa bởi người dùng này');
            }
        }

        // Increment usage count
        $this->increment('usage_count');

        // Attach to booking order
        $this->bookingOrders()->attach($bookingOrderId, [
            'applied_discount_amount' => $appliedAmount ?? 0,
        ]);

        return true;
    }

    /**
     * Check if promotion is applicable to specific room
     */
    public function isApplicableToRoom($roomId): bool
    {
        if ($this->applicable_to === 'all') {
            return true;
        }

        if ($this->applicable_to === 'specific_rooms') {
            return $this->rooms()->where('room_id', $roomId)->exists();
        }

        if ($this->applicable_to === 'specific_room_types') {
            $room = Room::find($roomId);
            return $room && $this->roomTypes()->where('room_type_id', $room->room_type_id)->exists();
        }

        return false;
    }
}
