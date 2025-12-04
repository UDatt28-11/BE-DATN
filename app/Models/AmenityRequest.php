<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmenityRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_details_id',
        'amenity_id',
        'quantity',
        'price_at_request',
        'status',
        'notes',
        'admin_notes',
        'approved_at',
        'rejected_at',
        'completed_at',
        'processed_by',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price_at_request' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // Relationships

    /**
     * Booking detail liên quan
     */
    public function detail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }

    /**
     * Alias cho detail
     */
    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }

    /**
     * Tiện ích được yêu cầu
     */
    public function amenity(): BelongsTo
    {
        return $this->belongsTo(Amenity::class);
    }

    /**
     * Admin/Staff xử lý yêu cầu
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    // Scopes

    /**
     * Scope để lọc theo status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope để lấy pending requests
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope để lấy approved requests
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // Helper methods

    /**
     * Tính tổng tiền (nếu có)
     */
    public function getTotalAmountAttribute(): float
    {
        if ($this->price_at_request) {
            return $this->price_at_request * $this->quantity;
        }
        return 0;
    }

    /**
     * Kiểm tra có thể approve không
     */
    public function canBeApproved(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Kiểm tra có thể reject không
     */
    public function canBeRejected(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Kiểm tra có thể complete không
     */
    public function canBeCompleted(): bool
    {
        return $this->status === 'approved';
    }
}


