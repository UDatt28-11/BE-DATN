<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingOrder;
use App\Models\Property;
use App\Models\User;
use App\Models\Room;
use App\Models\Invoice;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get dashboard overview
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'period' => 'sometimes|string|in:day,week,month',
            ], [
                'period.in' => 'Chu kỳ không hợp lệ. Chỉ chấp nhận: day, week, month.',
            ]);

            $dateFrom = $request->has('date_from') ? Carbon::parse($request->date_from) : Carbon::now()->subDays(30);
            $dateTo = $request->has('date_to') ? Carbon::parse($request->date_to) : Carbon::now();
            $period = $request->get('period', 'day');

            // Calculate totals
            $totalRevenue = BookingOrder::where('status', 'completed')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->sum('total_amount');

            $totalBookings = BookingOrder::whereBetween('created_at', [$dateFrom, $dateTo])
                ->count();

            $totalProperties = Property::count();

            $totalUsers = User::where('role', '!=', 'admin')->count();

            // Calculate previous period for growth comparison
            $periodDays = $dateFrom->diffInDays($dateTo);
            $prevDateFrom = $dateFrom->copy()->subDays($periodDays + 1);
            $prevDateTo = $dateFrom->copy()->subDay();

            $prevTotalRevenue = BookingOrder::where('status', 'completed')
                ->whereBetween('created_at', [$prevDateFrom, $prevDateTo])
                ->sum('total_amount');

            $prevTotalBookings = BookingOrder::whereBetween('created_at', [$prevDateFrom, $prevDateTo])
                ->count();

            // Calculate growth percentages
            $revenueGrowth = $prevTotalRevenue > 0 
                ? round((($totalRevenue - $prevTotalRevenue) / $prevTotalRevenue) * 100, 2)
                : 0;

            $bookingsGrowth = $prevTotalBookings > 0
                ? round((($totalBookings - $prevTotalBookings) / $prevTotalBookings) * 100, 2)
                : 0;

            // Top properties by revenue (formatted for frontend)
            $topProperties = $this->getTopPropertiesByRevenue($dateFrom, $dateTo);
            $topPropertiesFormatted = array_map(function ($item) {
                return [
                    'id' => $item['property_id'],
                    'name' => $item['property_name'],
                    'revenue' => $item['revenue'],
                    'bookings_count' => $item['booking_count'],
                ];
            }, $topProperties);

            // Recent bookings (formatted for frontend)
            $recentBookings = BookingOrder::with(['guest:id,full_name,email', 'details.room.property:id,name'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_code' => $order->order_code,
                        'customer_name' => $order->guest->full_name ?? $order->customer_name ?? 'N/A',
                        'property_name' => $order->details->first()?->room?->property?->name ?? 'N/A',
                        'total_amount' => (float) $order->total_amount,
                        'status' => $order->status,
                        'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    ];
                })
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_properties' => (int) $totalProperties,
                    'total_bookings' => (int) $totalBookings,
                    'total_users' => (int) $totalUsers,
                    'total_revenue' => (float) $totalRevenue,
                    'revenue_growth' => $revenueGrowth,
                    'bookings_growth' => $bookingsGrowth,
                    'recent_bookings' => $recentBookings,
                    'top_properties' => $topPropertiesFormatted,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyticsController@dashboard failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy dữ liệu dashboard.',
            ], 500);
        }
    }

    /**
     * Get revenue statistics
     */
    public function revenue(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'property_id' => 'sometimes|integer|exists:properties,id',
                'period' => 'sometimes|string|in:day,week,month',
            ], [
                'property_id.exists' => 'Property không tồn tại.',
                'period.in' => 'Chu kỳ không hợp lệ. Chỉ chấp nhận: day, week, month.',
            ]);

            $dateFrom = $request->has('date_from') ? Carbon::parse($request->date_from) : Carbon::now()->subDays(30);
            $dateTo = $request->has('date_to') ? Carbon::parse($request->date_to) : Carbon::now();
            $period = $request->get('period', 'day');

            // Revenue by period
            $byPeriod = $this->getRevenueByPeriod($dateFrom, $dateTo, $period);

            // Revenue by property
            $byProperty = $this->getRevenueByProperty($dateFrom, $dateTo, $request->property_id);

            // Revenue by location (if properties have location data)
            $byLocation = $this->getRevenueByLocation($dateFrom, $dateTo);

            // Total revenue
            $totalRevenue = BookingOrder::where('status', 'completed')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->sum('total_amount');

            $expectedRevenue = BookingOrder::whereIn('status', ['confirmed', 'completed'])
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->sum('total_amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => (float) $totalRevenue,
                    'expected_revenue' => (float) $expectedRevenue,
                    'by_period' => $byPeriod,
                    'by_property' => $byProperty,
                    'by_location' => $byLocation,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyticsController@revenue failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thống kê doanh thu.',
            ], 500);
        }
    }

    /**
     * Get customer statistics
     */
    public function customers(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
            ]);

            $dateFrom = $request->has('date_from') ? Carbon::parse($request->date_from) : Carbon::now()->subDays(30);
            $dateTo = $request->has('date_to') ? Carbon::parse($request->date_to) : Carbon::now();

            // Top customers by bookings
            $topByBookings = $this->getTopCustomersByBookings($dateFrom, $dateTo);

            // Top customers by revenue
            $topByRevenue = $this->getTopCustomersByRevenue($dateFrom, $dateTo);

            // Customers with most cancellations
            $topCancellations = $this->getTopCustomersByCancellations($dateFrom, $dateTo);

            return response()->json([
                'success' => true,
                'data' => [
                    'top_by_bookings' => $topByBookings,
                    'top_by_revenue' => $topByRevenue,
                    'top_cancellations' => $topCancellations,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyticsController@customers failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thống kê khách hàng.',
            ], 500);
        }
    }

    /**
     * Get booking statistics
     */
    public function bookings(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
                'period' => 'sometimes|string|in:day,week,month',
            ], [
                'period.in' => 'Chu kỳ không hợp lệ. Chỉ chấp nhận: day, week, month.',
            ]);

            $dateFrom = $request->has('date_from') ? Carbon::parse($request->date_from) : Carbon::now()->subDays(30);
            $dateTo = $request->has('date_to') ? Carbon::parse($request->date_to) : Carbon::now();
            $period = $request->get('period', 'day');

            // Bookings by period
            $byPeriod = $this->getBookingsByPeriod($dateFrom, $dateTo, $period);

            // Bookings by status
            $byStatus = $this->getBookingsByStatus($dateFrom, $dateTo);

            // Peak booking times
            $peakTimes = $this->getPeakBookingTimes($dateFrom, $dateTo);

            // Properties with most/least cancellations
            $cancellationStats = $this->getCancellationStats($dateFrom, $dateTo);

            return response()->json([
                'success' => true,
                'data' => [
                    'by_period' => $byPeriod,
                    'by_status' => $byStatus,
                    'peak_times' => $peakTimes,
                    'cancellation_stats' => $cancellationStats,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyticsController@bookings failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thống kê đặt phòng.',
            ], 500);
        }
    }

    /**
     * Get property statistics
     */
    public function properties(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date_from' => 'sometimes|date',
                'date_to' => 'sometimes|date|after_or_equal:date_from',
            ]);

            $dateFrom = $request->has('date_from') ? Carbon::parse($request->date_from) : Carbon::now()->subDays(30);
            $dateTo = $request->has('date_to') ? Carbon::parse($request->date_to) : Carbon::now();

            // Properties with availability calendar
            $availability = $this->getPropertyAvailability($dateFrom, $dateTo);

            // Properties with refund rates
            $refundRates = $this->getPropertyRefundRates($dateFrom, $dateTo);

            // Properties performance
            $performance = $this->getPropertyPerformance($dateFrom, $dateTo);

            return response()->json([
                'success' => true,
                'data' => [
                    'availability' => $availability,
                    'refund_rates' => $refundRates,
                    'performance' => $performance,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyticsController@properties failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thống kê homestay.',
            ], 500);
        }
    }

    // Private helper methods

    private function getRevenueOverview($dateFrom, $dateTo, $period): array
    {
        $format = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return BookingOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as period, SUM(total_amount) as revenue")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'revenue' => (float) $item->revenue,
                ];
            })
            ->toArray();
    }

    private function getBookingOverview($dateFrom, $dateTo, $period): array
    {
        $format = match ($period) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        return BookingOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw("DATE_FORMAT(created_at, '{$format}') as period, COUNT(*) as count, SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(function ($item) {
                return [
                    'period' => $item->period,
                    'count' => (int) $item->count,
                    'cancelled' => (int) $item->cancelled,
                    'cancellation_rate' => $item->count > 0 ? round(($item->cancelled / $item->count) * 100, 2) : 0,
                ];
            })
            ->toArray();
    }

    private function getTopPropertiesByRevenue($dateFrom, $dateTo, $limit = 10): array
    {
        return BookingOrder::join('booking_details', 'booking_orders.id', '=', 'booking_details.booking_order_id')
            ->join('rooms', 'booking_details.room_id', '=', 'rooms.id')
            ->join('properties', 'rooms.property_id', '=', 'properties.id')
            ->whereBetween('booking_orders.created_at', [$dateFrom, $dateTo])
            ->where('booking_orders.status', 'completed')
            ->selectRaw('properties.id, properties.name, SUM(booking_orders.total_amount) as revenue, COUNT(DISTINCT booking_orders.id) as booking_count')
            ->groupBy('properties.id', 'properties.name')
            ->orderBy('revenue', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'property_id' => $item->id,
                    'property_name' => $item->name,
                    'revenue' => (float) $item->revenue,
                    'booking_count' => (int) $item->booking_count,
                ];
            })
            ->toArray();
    }

    private function getTopCustomers($dateFrom, $dateTo, $limit = 10): array
    {
        return BookingOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'completed')
            ->selectRaw('guest_id, COUNT(*) as booking_count, SUM(total_amount) as total_spent')
            ->groupBy('guest_id')
            ->orderBy('total_spent', 'desc')
            ->limit($limit)
            ->with('guest:id,full_name,email')
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->guest_id,
                    'full_name' => $item->guest->full_name ?? 'N/A',
                    'email' => $item->guest->email ?? 'N/A',
                    'booking_count' => (int) $item->booking_count,
                    'total_spent' => (float) $item->total_spent,
                ];
            })
            ->toArray();
    }

    private function getRevenueByPeriod($dateFrom, $dateTo, $period): array
    {
        return $this->getRevenueOverview($dateFrom, $dateTo, $period);
    }

    private function getRevenueByProperty($dateFrom, $dateTo, $propertyId = null): array
    {
        $query = BookingOrder::join('booking_details', 'booking_orders.id', '=', 'booking_details.booking_order_id')
            ->join('rooms', 'booking_details.room_id', '=', 'rooms.id')
            ->join('properties', 'rooms.property_id', '=', 'properties.id')
            ->whereBetween('booking_orders.created_at', [$dateFrom, $dateTo])
            ->where('booking_orders.status', 'completed');

        if ($propertyId) {
            $query->where('properties.id', $propertyId);
        }

        return $query->selectRaw('properties.id, properties.name, SUM(booking_orders.total_amount) as revenue')
            ->groupBy('properties.id', 'properties.name')
            ->orderBy('revenue', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'property_id' => $item->id,
                    'property_name' => $item->name,
                    'revenue' => (float) $item->revenue,
                ];
            })
            ->toArray();
    }

    private function getRevenueByLocation($dateFrom, $dateTo): array
    {
        // Group by property address (assuming address contains location info)
        return BookingOrder::join('booking_details', 'booking_orders.id', '=', 'booking_details.booking_order_id')
            ->join('rooms', 'booking_details.room_id', '=', 'rooms.id')
            ->join('properties', 'rooms.property_id', '=', 'properties.id')
            ->whereBetween('booking_orders.created_at', [$dateFrom, $dateTo])
            ->where('booking_orders.status', 'completed')
            ->selectRaw('properties.address, SUM(booking_orders.total_amount) as revenue, COUNT(DISTINCT booking_orders.id) as booking_count')
            ->groupBy('properties.address')
            ->orderBy('revenue', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'location' => $item->address,
                    'revenue' => (float) $item->revenue,
                    'booking_count' => (int) $item->booking_count,
                ];
            })
            ->toArray();
    }

    private function getTopCustomersByBookings($dateFrom, $dateTo, $limit = 10): array
    {
        return BookingOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('guest_id, COUNT(*) as booking_count')
            ->groupBy('guest_id')
            ->orderBy('booking_count', 'desc')
            ->limit($limit)
            ->with('guest:id,full_name,email')
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->guest_id,
                    'full_name' => $item->guest->full_name ?? 'N/A',
                    'email' => $item->guest->email ?? 'N/A',
                    'booking_count' => (int) $item->booking_count,
                ];
            })
            ->toArray();
    }

    private function getTopCustomersByRevenue($dateFrom, $dateTo, $limit = 10): array
    {
        return $this->getTopCustomers($dateFrom, $dateTo, $limit);
    }

    private function getTopCustomersByCancellations($dateFrom, $dateTo, $limit = 10): array
    {
        return BookingOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('status', 'cancelled')
            ->selectRaw('guest_id, COUNT(*) as cancellation_count')
            ->groupBy('guest_id')
            ->orderBy('cancellation_count', 'desc')
            ->limit($limit)
            ->with('guest:id,full_name,email')
            ->get()
            ->map(function ($item) {
                return [
                    'user_id' => $item->guest_id,
                    'full_name' => $item->guest->full_name ?? 'N/A',
                    'email' => $item->guest->email ?? 'N/A',
                    'cancellation_count' => (int) $item->cancellation_count,
                ];
            })
            ->toArray();
    }

    private function getBookingsByPeriod($dateFrom, $dateTo, $period): array
    {
        return $this->getBookingOverview($dateFrom, $dateTo, $period);
    }

    private function getBookingsByStatus($dateFrom, $dateTo): array
    {
        return BookingOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->map(function ($item) {
                return [
                    'status' => $item->status,
                    'count' => (int) $item->count,
                ];
            })
            ->toArray();
    }

    private function getPeakBookingTimes($dateFrom, $dateTo): array
    {
        return BookingOrder::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('HOUR(created_at) as hour, DAYOFWEEK(created_at) as day_of_week, COUNT(*) as count')
            ->groupBy('hour', 'day_of_week')
            ->orderBy('count', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => (int) $item->hour,
                    'day_of_week' => (int) $item->day_of_week,
                    'count' => (int) $item->count,
                ];
            })
            ->toArray();
    }

    private function getCancellationStats($dateFrom, $dateTo): array
    {
        $mostCancellations = BookingOrder::join('booking_details', 'booking_orders.id', '=', 'booking_details.booking_order_id')
            ->join('rooms', 'booking_details.room_id', '=', 'rooms.id')
            ->join('properties', 'rooms.property_id', '=', 'properties.id')
            ->whereBetween('booking_orders.created_at', [$dateFrom, $dateTo])
            ->where('booking_orders.status', 'cancelled')
            ->selectRaw('properties.id, properties.name, COUNT(*) as cancellation_count')
            ->groupBy('properties.id', 'properties.name')
            ->orderBy('cancellation_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'property_id' => $item->id,
                    'property_name' => $item->name,
                    'cancellation_count' => (int) $item->cancellation_count,
                ];
            })
            ->toArray();

        $leastCancellations = BookingOrder::join('booking_details', 'booking_orders.id', '=', 'booking_details.booking_order_id')
            ->join('rooms', 'booking_details.room_id', '=', 'rooms.id')
            ->join('properties', 'rooms.property_id', '=', 'properties.id')
            ->whereBetween('booking_orders.created_at', [$dateFrom, $dateTo])
            ->selectRaw('properties.id, properties.name, COUNT(*) as total_bookings, SUM(CASE WHEN booking_orders.status = "cancelled" THEN 1 ELSE 0 END) as cancellation_count')
            ->groupBy('properties.id', 'properties.name')
            ->havingRaw('total_bookings > 0')
            ->orderByRaw('(cancellation_count / total_bookings) ASC')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'property_id' => $item->id,
                    'property_name' => $item->name,
                    'total_bookings' => (int) $item->total_bookings,
                    'cancellation_count' => (int) $item->cancellation_count,
                    'cancellation_rate' => $item->total_bookings > 0 ? round(($item->cancellation_count / $item->total_bookings) * 100, 2) : 0,
                ];
            })
            ->toArray();

        return [
            'most_cancellations' => $mostCancellations,
            'least_cancellations' => $leastCancellations,
        ];
    }

    private function getPropertyAvailability($dateFrom, $dateTo): array
    {
        // Get properties with their booking calendar
        return Property::with(['rooms.bookingDetails' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('check_in_date', [$dateFrom, $dateTo])
                ->orWhereBetween('check_out_date', [$dateFrom, $dateTo]);
        }])
            ->get()
            ->map(function ($property) use ($dateFrom, $dateTo) {
                $totalDays = $dateFrom->diffInDays($dateTo);
                $bookedDays = 0;

                foreach ($property->rooms as $room) {
                    foreach ($room->bookingDetails as $detail) {
                        $checkIn = Carbon::parse($detail->check_in_date);
                        $checkOut = Carbon::parse($detail->check_out_date);
                        $bookedDays += $checkIn->diffInDays($checkOut);
                    }
                }

                return [
                    'property_id' => $property->id,
                    'property_name' => $property->name,
                    'total_days' => $totalDays,
                    'booked_days' => $bookedDays,
                    'availability_rate' => $totalDays > 0 ? round((($totalDays - $bookedDays) / $totalDays) * 100, 2) : 0,
                ];
            })
            ->toArray();
    }

    private function getPropertyRefundRates($dateFrom, $dateTo): array
    {
        return BookingOrder::join('booking_details', 'booking_orders.id', '=', 'booking_details.booking_order_id')
            ->join('rooms', 'booking_details.room_id', '=', 'rooms.id')
            ->join('properties', 'rooms.property_id', '=', 'properties.id')
            ->join('invoices', 'booking_orders.id', '=', 'invoices.booking_order_id')
            ->whereBetween('booking_orders.created_at', [$dateFrom, $dateTo])
            ->selectRaw('properties.id, properties.name, SUM(invoices.refund_amount) as total_refunds, COUNT(DISTINCT booking_orders.id) as total_bookings')
            ->groupBy('properties.id', 'properties.name')
            ->get()
            ->map(function ($item) {
                return [
                    'property_id' => $item->id,
                    'property_name' => $item->name,
                    'total_refunds' => (float) $item->total_refunds,
                    'total_bookings' => (int) $item->total_bookings,
                    'refund_rate' => $item->total_bookings > 0 ? round(($item->total_refunds / ($item->total_bookings * 1000000)) * 100, 2) : 0, // Simplified calculation
                ];
            })
            ->toArray();
    }

    private function getPropertyPerformance($dateFrom, $dateTo): array
    {
        return Property::withCount(['rooms', 'bookingOrders' => function ($query) use ($dateFrom, $dateTo) {
            $query->whereBetween('created_at', [$dateFrom, $dateTo]);
        }])
            ->get()
            ->map(function ($property) use ($dateFrom, $dateTo) {
                $revenue = BookingOrder::join('booking_details', 'booking_orders.id', '=', 'booking_details.booking_order_id')
                    ->join('rooms', 'booking_details.room_id', '=', 'rooms.id')
                    ->where('rooms.property_id', $property->id)
                    ->whereBetween('booking_orders.created_at', [$dateFrom, $dateTo])
                    ->where('booking_orders.status', 'completed')
                    ->sum('booking_orders.total_amount');

                return [
                    'property_id' => $property->id,
                    'property_name' => $property->name,
                    'rooms_count' => $property->rooms_count,
                    'bookings_count' => $property->booking_orders_count,
                    'revenue' => (float) $revenue,
                ];
            })
            ->toArray();
    }

    /**
     * Public statistics for homepage (không cần đăng nhập)
     */
    public function publicStatistics(): JsonResponse
    {
        try {
            // Tổng số properties
            try {
                $totalProperties = Property::where('verification_status', 'verified')->count();
            } catch (\Exception $e) {
                $totalProperties = Property::count();
            }
            
            // Tổng số users (không tính admin)
            $totalUsers = User::where('role', '!=', 'admin')->count();
            
            // Tổng số rooms
            try {
                $totalRooms = Room::where('verification_status', 'verified')
                    ->where('status', 'available')
                    ->count();
            } catch (\Exception $e) {
                $totalRooms = Room::where('status', 'available')->count();
            }
            
            // Tổng số reviews với rating 5 sao
            try {
                $totalFiveStarReviews = Review::where('rating', 5)
                    ->where('status', 'approved')
                    ->count();
            } catch (\Exception $e) {
                $totalFiveStarReviews = Review::where('rating', 5)->count();
            }
            
            // Đếm số lượng room types
            try {
                $totalRoomTypes = \App\Models\RoomType::where('status', 'active')->count();
            } catch (\Exception $e) {
                $totalRoomTypes = \App\Models\RoomType::count();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'total_properties' => (int) $totalProperties,
                    'total_users' => (int) $totalUsers,
                    'total_rooms' => (int) $totalRooms,
                    'total_five_star_reviews' => (int) $totalFiveStarReviews,
                    'total_room_types' => (int) $totalRoomTypes,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyticsController@publicStatistics failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy dữ liệu thống kê.',
            ], 500);
        }
    }
}

