<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\JsonResponse;

trait CachesApiResponses
{
    /**
     * Cache durations in seconds
     */
    protected int $shortCache = 60;      // 1 minute
    protected int $mediumCache = 300;    // 5 minutes  
    protected int $longCache = 3600;     // 1 hour
    protected int $veryLongCache = 86400; // 24 hours

    /**
     * Get cached response or execute callback and cache result
     */
    protected function cacheResponse(string $key, callable $callback, int $duration = 300): JsonResponse
    {
        $cacheKey = 'api_' . md5($key);
        
        $data = Cache::remember($cacheKey, $duration, function () use ($callback) {
            return $callback();
        });

        return response()->json($data);
    }

    /**
     * Clear cache by key pattern
     */
    protected function clearCacheByPattern(string $pattern): void
    {
        // For file/database cache driver, we need to track keys
        // For Redis, we can use pattern matching
        $driver = config('cache.default');
        
        if ($driver === 'redis') {
            $redis = Cache::getRedis();
            $keys = $redis->keys('*' . $pattern . '*');
            foreach ($keys as $key) {
                Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
            }
        }
    }

    /**
     * Clear all API cache
     */
    protected function clearApiCache(): void
    {
        Cache::flush(); // Be careful with this in production
    }

    /**
     * Generate cache key from request
     */
    protected function getCacheKeyFromRequest(): string
    {
        return request()->fullUrl();
    }
}

