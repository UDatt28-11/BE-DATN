<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'booking_details_id',
        'user_id',
        'property_id',
        'room_id',
        'rating',
        'title',
        'comment',
        'photos',
        'is_verified_purchase',
        'is_helpful_count',
        'is_not_helpful_count',
        'status', // pending, approved, rejected
        'admin_notes',
        'reviewed_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'photos' => 'array',
        'is_verified_purchase' => 'boolean',
        'is_helpful_count' => 'integer',
        'is_not_helpful_count' => 'integer',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['helpful_percentage'];

    // Relationships
    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'booking_details_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByProperty($query, $propertyId)
    {
        return $query->where('property_id', $propertyId);
    }

    public function scopeByRating($query, $rating)
    {
        return $query->where('rating', $rating);
    }

    public function scopeVerifiedPurchase($query)
    {
        return $query->where('is_verified_purchase', true);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('reviewed_at', '>=', now()->subDays($days));
    }

    // Accessors & Mutators
    public function getHelpfulPercentageAttribute(): float
    {
        $total = $this->is_helpful_count + $this->is_not_helpful_count;
        if ($total === 0) {
            return 0;
        }
        return round(($this->is_helpful_count / $total) * 100, 2);
    }

    // Methods
    /**
     * Approve review
     */
    public function approve($adminNotes = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_at' => now(),
            'admin_notes' => $adminNotes,
        ]);
    }

    /**
     * Reject review
     */
    public function reject($adminNotes): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_at' => now(),
            'admin_notes' => $adminNotes,
        ]);
    }

    /**
     * Mark as helpful
     */
    public function markAsHelpful(): void
    {
        $this->increment('is_helpful_count');
    }

    /**
     * Mark as not helpful
     */
    public function markAsNotHelpful(): void
    {
        $this->increment('is_not_helpful_count');
    }

    /**
     * Get average rating for property
     */
    public static function getPropertyAverageRating($propertyId, $includeUnverified = false): float
    {
        $query = self::byProperty($propertyId)->approved();

        if (!$includeUnverified) {
            $query->verifiedPurchase();
        }

        $average = $query->average('rating');
        return round($average ?? 0, 2);
    }

    /**
     * Get rating distribution for property
     */
    public static function getPropertyRatingDistribution($propertyId, $includeUnverified = false): array
    {
        $query = self::byProperty($propertyId)->approved();

        if (!$includeUnverified) {
            $query->verifiedPurchase();
        }

        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = (clone $query)->byRating($i)->count();
            $distribution[$i] = $count;
        }

        return $distribution;
    }

    /**
     * Check if user already reviewed this booking
     */
    public static function hasUserReviewedBooking($userId, $bookingDetailId): bool
    {
        return self::where('user_id', $userId)
            ->where('booking_details_id', $bookingDetailId)
            ->exists();
    }
}
