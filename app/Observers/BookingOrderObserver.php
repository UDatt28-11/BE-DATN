<?php

namespace App\Observers;

use App\Models\BookingOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BookingOrderObserver
{
    /**
     * Handle the BookingOrder "created" event.
     */
    public function created(BookingOrder $bookingOrder): void
    {
        $this->clearRoomTypesCache();
    }

    /**
     * Handle the BookingOrder "updated" event.
     */
    public function updated(BookingOrder $bookingOrder): void
    {
        // Only clear cache if status changed
        if ($bookingOrder->isDirty('status')) {
            $this->clearRoomTypesCache();
        }
    }

    /**
     * Handle the BookingOrder "deleted" event.
     */
    public function deleted(BookingOrder $bookingOrder): void
    {
        $this->clearRoomTypesCache();
    }

    /**
     * Clear all room types cache entries
     */
    private function clearRoomTypesCache(): void
    {
        try {
            $pattern = 'room_types_*';
            
            // For Redis
            if (config('cache.default') === 'redis') {
                $redis = Cache::getRedis();
                $prefix = config('cache.prefix') ? config('cache.prefix') . ':' : '';
                $keys = $redis->keys($prefix . $pattern);
                
                foreach ($keys as $key) {
                    $cleanKey = str_replace($prefix, '', $key);
                    Cache::forget($cleanKey);
                }
                
                Log::info('Room types cache cleared', ['count' => count($keys)]);
            } else {
                // For other drivers
                Cache::flush();
                Log::info('All cache cleared (non-Redis)');
            }
        } catch (\Exception $e) {
            Log::error('Failed to clear room types cache', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
