<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheApiResponse
{
    /**
     * Cache duration in seconds (5 minutes by default)
     */
    protected int $cacheDuration = 300;

    /**
     * Routes that should not be cached
     */
    protected array $excludedRoutes = [
        'login',
        'register',
        'logout',
        'booking',
        'payment',
        'cart',
        'profile',
        'admin',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $duration = 300): Response
    {
        // Allow preflight OPTIONS requests to pass through (CORS)
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Don't cache for authenticated users (personalized content)
        if ($request->user()) {
            return $next($request);
        }

        // Don't cache excluded routes
        foreach ($this->excludedRoutes as $route) {
            if (str_contains($request->path(), $route)) {
                return $next($request);
            }
        }

        // Generate cache key from URL and query params
        $cacheKey = 'api_cache_' . md5($request->fullUrl());

        // Try to get from cache
        $cachedResponse = Cache::get($cacheKey);
        
        if ($cachedResponse) {
            $response = response()
                ->json($cachedResponse['data'], $cachedResponse['status'])
                ->header('X-Cache', 'HIT')
                ->header('X-Cache-Key', substr($cacheKey, 0, 20) . '...');
            
            // Add CORS headers to cached response
            return $this->addCorsHeaders($response, $request);
        }

        // Get the response
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful()) {
            $content = json_decode($response->getContent(), true);
            
            if ($content !== null) {
                Cache::put($cacheKey, [
                    'data' => $content,
                    'status' => $response->getStatusCode(),
                ], $duration);

                $response->header('X-Cache', 'MISS');
            }
        }

        return $response;
    }

    /**
     * Add CORS headers to response
     */
    protected function addCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->header('Origin', '*');
        
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        return $response;
    }
}

