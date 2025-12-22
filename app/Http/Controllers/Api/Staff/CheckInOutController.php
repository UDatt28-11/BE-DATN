<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\BookingOrder;
use App\Models\BookingDetail;
use App\Models\CheckedInGuest;
use App\Models\Room;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Supply;
use App\Models\SupplyLog;
use App\Models\Service;
use App\Models\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Check-in/Check-out Controller for Staff
 */
class CheckInOutController extends Controller
{
    /**
     * Get list of bookings ready for check-in
     * Lấy danh sách booking sẵn sàng để check-in (status: confirmed, check_in_date = today)
     */
    public function getCheckInList(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'sometimes|date',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $date = $request->get('date', now()->toDateString());
            $perPage = (int) ($request->get('per_page', 15));

            $query = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'details.room:id,name,property_id',
                'details.room.property:id,name,address',
                'details.checkedInGuests',
            ])
            ->whereIn('status', ['confirmed', 'pending', 'partially_checked_in'])
            ->whereHas('details', function ($q) use ($date) {
                $q->whereDate('check_in_date', '<=', $date)
                  ->whereIn('status', ['active', 'checked_in']);
            });

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', '%' . $search . '%')
                      ->orWhere('customer_name', 'like', '%' . $search . '%')
                      ->orWhere('customer_phone', 'like', '%' . $search . '%')
                      ->orWhere('customer_email', 'like', '%' . $search . '%');
                });
            }

            $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $bookings->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $bookings->currentPage(),
                        'per_page' => $bookings->perPage(),
                        'total' => $bookings->total(),
                        'last_page' => $bookings->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getCheckInList failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách check-in.',
            ], 500);
        }
    }

    /**
     * Get booking details for check-in
     */
    public function getCheckInDetails(string $id): JsonResponse
    {
        try {
            $booking = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'staff:id,full_name,email',
                'details.room:id,name,property_id,status',
                'details.room.property:id,name,address',
                'details.checkedInGuests',
                'details.bookingServices.service:id,name,unit_price',
            ])->findOrFail($id);

            // Log booking status để debug
            Log::info('CheckInOutController@getCheckInDetails - Booking status', [
                'booking_id' => $id,
                'booking_status' => $booking->status,
                'details_count' => $booking->details->count(),
            ]);

            // Kiểm tra booking có thể check-in không
            if (!in_array($booking->status, ['confirmed', 'pending', 'partially_checked_in'])) {
                Log::warning('CheckInOutController@getCheckInDetails - Invalid booking status', [
                    'booking_id' => $id,
                    'current_status' => $booking->status,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-in. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $booking,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getCheckInDetails failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin check-in.',
            ], 500);
        }
    }

    /**
     * Process check-in
     * Xử lý check-in: lưu thông tin khách, upload giấy tờ, cập nhật trạng thái
     */
    public function checkIn(Request $request, string $id): JsonResponse
    {
        try {
            // Log request để debug
            Log::info('CheckInOutController@checkIn - Request received', [
                'booking_id' => $id,
                'has_guests' => $request->has('guests'),
                'guests_count' => $request->has('guests') ? count($request->input('guests', [])) : 0,
                'request_data' => $request->except(['guests.*.identity_image']), // Exclude file data
            ]);

            $request->validate([
                'guests' => 'required|array|min:1',
                'guests.*.full_name' => 'required|string|max:255',
                'guests.*.date_of_birth' => 'nullable|date',
                'guests.*.identity_type' => 'required|in:cccd,passport',
                'guests.*.identity_number' => 'required|string|max:50',
                'guests.*.identity_image' => 'nullable|image|mimes:jpeg,png,jpg|max:5120', // 5MB
                'guests.*.booking_detail_id' => 'required|exists:booking_details,id',
                'notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with(['details.room'])->findOrFail($id);

            // Log booking status
            Log::info('CheckInOutController@checkIn - Booking status check', [
                'booking_id' => $id,
                'booking_status' => $booking->status,
                'allowed_statuses' => ['confirmed', 'pending', 'partially_checked_in'],
            ]);

            // Kiểm tra booking có thể check-in không
            if (!in_array($booking->status, ['confirmed', 'pending', 'partially_checked_in'])) {
                Log::warning('CheckInOutController@checkIn - Invalid booking status', [
                    'booking_id' => $id,
                    'current_status' => $booking->status,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-in. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            // Lưu danh sách booking_detail_id đã có guests check-in
            $checkedInDetailIds = [];

            // Xử lý từng guest
            foreach ($request->guests as $index => $guestData) {
                $bookingDetail = BookingDetail::findOrFail($guestData['booking_detail_id']);

                // Kiểm tra booking detail thuộc về booking order này
                if ($bookingDetail->booking_order_id != $booking->id) {
                    throw new \Exception('Booking detail không thuộc về booking order này.');
                }

                // Validate: Chỉ cho phép check-in vào đúng ngày check-in của booking
                if ($bookingDetail->check_in_date) {
                    // Sử dụng timezone của ứng dụng để so sánh ngày
                    $checkInDate = \Carbon\Carbon::parse($bookingDetail->check_in_date)
                        ->setTimezone(config('app.timezone'))
                        ->startOfDay();
                    $today = \Carbon\Carbon::now(config('app.timezone'))->startOfDay();
                    
                    if (!$today->equalTo($checkInDate)) {
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Không thể check-in. Phòng này chỉ có thể check-in vào ngày " . $checkInDate->format('d/m/Y') . ". Ngày hiện tại: " . $today->format('d/m/Y'),
                        ], 400);
                    }
                }

                // Kiểm tra booking detail đã check-in chưa (nếu đã check-in thì bỏ qua, không cần check-in lại)
                // Cho phép check-in lại nếu cần thêm khách
                // if ($bookingDetail->status === 'checked_in') {
                //     Log::info('CheckInOutController@checkIn - Booking detail already checked in', [
                //         'booking_detail_id' => $bookingDetail->id,
                //     ]);
                //     continue; // Bỏ qua nếu đã check-in
                // }

                // Upload identity image nếu có
                // File được gửi với key: guests[0][identity_image] hoặc guests.0.identity_image
                $identityImageUrl = null;
                $fileKey = "guests.{$index}.identity_image";
                if ($request->hasFile($fileKey)) {
                    $file = $request->file($fileKey);
                    $identityImageUrl = $this->storeIdentityImage($file);
                }

                // Tạo CheckedInGuest record
                CheckedInGuest::create([
                    'booking_details_id' => $bookingDetail->id,
                    'full_name' => $guestData['full_name'],
                    'date_of_birth' => $guestData['date_of_birth'] ?? null,
                    'identity_type' => $guestData['identity_type'],
                    'identity_number' => $guestData['identity_number'],
                    'identity_image_url' => $identityImageUrl,
                    'check_in_time' => now(),
                ]);

                // Lưu booking_detail_id đã có guests check-in
                if (!in_array($bookingDetail->id, $checkedInDetailIds)) {
                    $checkedInDetailIds[] = $bookingDetail->id;
                }
            }

            // Cập nhật trạng thái booking details - CHỈ các phòng có guests check-in
            BookingDetail::whereIn('id', $checkedInDetailIds)->update(['status' => 'checked_in']);

            // Cập nhật trạng thái phòng thành "occupied" - CHỈ các phòng đã check-in
            $checkedInDetails = BookingDetail::whereIn('id', $checkedInDetailIds)->with('room')->get();
            foreach ($checkedInDetails as $detail) {
                if ($detail->room) {
                    $detail->room->update(['status' => 'occupied']);
                }
            }

            // Refresh booking model để có dữ liệu mới nhất
            $booking->refresh();
            $booking->load('details');

            // Kiểm tra xem tất cả phòng đã check-in chưa
            $totalDetails = $booking->details()->count();
            $checkedInCount = $booking->details()->where('status', 'checked_in')->count();
            
            // Cập nhật trạng thái booking order
            $newStatus = ($checkedInCount >= $totalDetails) ? 'checked_in' : 'partially_checked_in';
            $booking->update([
                'status' => $newStatus,
                'staff_id' => Auth::id(),
                'notes' => $request->notes ?? $booking->notes,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Check-in thành công.',
                'data' => $booking->fresh(['guest', 'details.room', 'details.checkedInGuests']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CheckInOutController@checkIn failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi check-in: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of bookings ready for check-out
     */
    public function getCheckOutList(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'sometimes|date',
                'search' => 'sometimes|string|max:255',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ]);

            $date = $request->get('date', now()->toDateString());
            $perPage = (int) ($request->get('per_page', 15));

            $query = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'details.room:id,name,property_id',
                'details.room.property:id,name,address',
                'details.checkedInGuests',
                'invoices',
            ])
            ->whereIn('status', ['checked_in', 'partially_checked_in', 'partially_checked_out'])
            ->whereHas('details', function ($q) use ($date) {
                // Chỉ lấy các phòng đã check-in và có ngày check-out <= hôm nay
                $q->whereIn('status', ['checked_in'])
                  ->whereDate('check_out_date', '<=', $date);
            });

            // Log để debug
            Log::info('CheckInOutController@getCheckOutList - Query conditions', [
                'date' => $date,
                'statuses' => ['checked_in', 'partially_checked_in', 'partially_checked_out'],
                'count_before_paginate' => $query->count(),
            ]);

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('order_code', 'like', '%' . $search . '%')
                      ->orWhere('customer_name', 'like', '%' . $search . '%')
                      ->orWhere('customer_phone', 'like', '%' . $search . '%');
                });
            }

            $bookings = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $bookings->items(),
                'meta' => [
                    'pagination' => [
                        'current_page' => $bookings->currentPage(),
                        'per_page' => $bookings->perPage(),
                        'total' => $bookings->total(),
                        'last_page' => $bookings->lastPage(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getCheckOutList failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách check-out.',
            ], 500);
        }
    }

    /**
     * Get booking details for check-out
     */
    public function getCheckOutDetails(string $id): JsonResponse
    {
        try {
            $booking = BookingOrder::with([
                'guest:id,full_name,email,phone_number',
                'staff:id,full_name,email',
                'details.room:id,name,property_id,status',
                'details.room.property:id,name,address',
                'details.checkedInGuests',
                'details.bookingServices.service:id,name,unit_price',
                'invoices.invoiceItems',
            ])->findOrFail($id);

            // Kiểm tra booking có thể check-out không
            if (!in_array($booking->status, ['checked_in', 'partially_checked_in', 'partially_checked_out'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-out. Trạng thái hiện tại: ' . $booking->status,
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $booking,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn đặt phòng.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getCheckOutDetails failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin check-out.',
            ], 500);
        }
    }

    /**
     * Process check-out
     * Xử lý check-out: kiểm tra vật tư/dịch vụ, tính phí phát sinh, tạo invoice, cập nhật trạng thái phòng
     */
    public function checkOut(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'booking_detail_ids' => 'nullable|array|min:1',
                'booking_detail_ids.*' => 'required|exists:booking_details,id',
                'room_status' => 'required|in:available,maintenance',
                'additional_services' => 'nullable|array',
                'additional_services.*.service_id' => 'required|exists:services,id',
                'additional_services.*.quantity' => 'required|integer|min:1',
                'damaged_supplies' => 'nullable|array',
                'damaged_supplies.*.supply_id' => 'required|exists:supplies,id',
                'damaged_supplies.*.quantity' => 'required|integer|min:1',
                'damaged_supplies.*.unit_price' => 'nullable|numeric|min:0',
                'damaged_supplies.*.notes' => 'nullable|string|max:500',
                'notes' => 'nullable|string|max:1000',
                'create_invoice' => 'nullable|boolean',
            ]);

            DB::beginTransaction();

            $booking = BookingOrder::with(['details.room', 'invoices.invoiceItems'])->findOrFail($id);

            // Kiểm tra booking có thể check-out không
            if (!in_array($booking->status, ['checked_in', 'partially_checked_in', 'partially_checked_out'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Đơn đặt phòng này không thể check-out.',
                ], 400);
            }

            // Lấy danh sách booking_detail_ids cần check-out
            // Nếu không có booking_detail_ids, check-out tất cả phòng đã check-in
            $checkOutDetailIds = $request->booking_detail_ids ?? $booking->details()->where('status', 'checked_in')->pluck('id')->toArray();
            
            if (empty($checkOutDetailIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không có phòng nào để check-out.',
                ], 400);
            }
            
            // Kiểm tra các booking_detail thuộc về booking order này
            $validDetails = $booking->details()->whereIn('id', $checkOutDetailIds)->get();
            if ($validDetails->count() !== count($checkOutDetailIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Một hoặc nhiều phòng không thuộc về đơn đặt phòng này.',
                ], 400);
            }

            // Lấy hoặc tạo invoice cho booking
            $invoice = $booking->invoices()->first();
            if (!$invoice) {
                $invoice = Invoice::create([
                    'booking_order_id' => $booking->id,
                    'invoice_code' => 'INV-' . strtoupper(Str::random(8)),
                    'total_amount' => $booking->total_amount,
                    'paid_amount' => $booking->deposit_amount ?? 0,
                    'status' => 'pending',
                    'issued_date' => now(),
                    'due_date' => now()->addDays(7),
                ]);
            }

            // Biến để theo dõi tổng phí thiệt hại
            $totalDamageFee = 0;
            $damageItems = [];

            // Xử lý vật tư bị hỏng (nếu có)
            if ($request->has('damaged_supplies') && !empty($request->damaged_supplies)) {
                $allFiles = $request->allFiles();
                
                foreach ($request->damaged_supplies as $index => $damagedItem) {
                    $supply = Supply::findOrFail($damagedItem['supply_id']);
                    $quantity = (int) $damagedItem['quantity'];
                    $unitPrice = $damagedItem['unit_price'] ?? $supply->unit_price;
                    $totalLine = $quantity * $unitPrice;
                    $notes = $damagedItem['notes'] ?? '';

                    // Tạo InvoiceItem cho thiệt hại
                    $invoiceItem = InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => "Thiệt hại vật tư: {$supply->name}" . ($notes ? " - {$notes}" : ''),
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_line' => $totalLine,
                        'item_type' => 'damage_fee',
                    ]);

                    // Xử lý upload ảnh minh chứng thiệt hại
                    $damageImages = [];
                    
                    // Tìm files từ request
                    if (isset($allFiles['damaged_supplies'][$index]['damage_images'])) {
                        $files = $allFiles['damaged_supplies'][$index]['damage_images'];
                        if (!is_array($files)) {
                            $files = [$files];
                        }
                        
                        foreach ($files as $fileIndex => $file) {
                            if ($file && $file->isValid()) {
                                try {
                                    $directory = 'damage_images';
                                    $extension = $file->getClientOriginalExtension();
                                    $filename = \Illuminate\Support\Str::uuid() . '.' . $extension;
                                    $path = Storage::disk('s3')->putFileAs($directory, $file, $filename);
                                    
                                    if ($path) {
                                        $url = Storage::disk('s3')->url($path);
                                        
                                        // Tạo DamageImage record
                                        \App\Models\DamageImage::create([
                                            'invoice_item_id' => $invoiceItem->id,
                                            'image_url' => $url,
                                            'order' => $fileIndex,
                                        ]);
                                        
                                        $damageImages[] = $url;
                                        
                                        Log::info('Damage image uploaded successfully', [
                                            'invoice_item_id' => $invoiceItem->id,
                                            'supply_id' => $supply->id,
                                            'image_url' => $url,
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Error uploading damage image', [
                                        'invoice_item_id' => $invoiceItem->id,
                                        'error' => $e->getMessage(),
                                    ]);
                                }
                            }
                        }
                    }

                    // Ghi log vào supply_logs
                    SupplyLog::create([
                        'supply_id' => $supply->id,
                        'user_id' => Auth::id(),
                        'type' => 'damage',
                        'quantity_change' => -$quantity,
                        'quantity_before' => $supply->current_stock,
                        'quantity_after' => max(0, $supply->current_stock - $quantity),
                        'notes' => "Thiệt hại khi checkout booking #{$booking->order_code}. " . ($notes ? "Ghi chú: {$notes}" : ''),
                    ]);

                    // Cập nhật stock của supply
                    $supply->update([
                        'current_stock' => max(0, $supply->current_stock - $quantity),
                    ]);

                    $totalDamageFee += $totalLine;
                    $damageItems[] = [
                        'supply' => $supply->name,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total' => $totalLine,
                        'notes' => $notes,
                        'images' => $damageImages,
                    ];
                }

                // Cập nhật tổng tiền invoice
                $invoice->update([
                    'total_amount' => $invoice->total_amount + $totalDamageFee,
                ]);
            }

            // Xử lý dịch vụ phát sinh (nếu có)
            $totalServiceFee = 0;
            if ($request->has('additional_services') && !empty($request->additional_services)) {
                foreach ($request->additional_services as $serviceData) {
                    $service = Service::findOrFail($serviceData['service_id']);
                    $quantity = (int) $serviceData['quantity'];
                    $unitPrice = $service->unit_price;
                    $totalLine = $quantity * $unitPrice;

                    // Tạo InvoiceItem cho dịch vụ
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'description' => "Dịch vụ: {$service->name}",
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_line' => $totalLine,
                        'item_type' => 'service_charge',
                    ]);

                    $totalServiceFee += $totalLine;
                }

                // Cập nhật tổng tiền invoice
                $invoice->update([
                    'total_amount' => $invoice->total_amount + $totalServiceFee,
                ]);
            }

            // Cập nhật trạng thái booking details - CHỈ các phòng được chọn
            BookingDetail::whereIn('id', $checkOutDetailIds)->update(['status' => 'checked_out']);

            // Cập nhật trạng thái phòng - CHỈ các phòng được check-out
            foreach ($validDetails as $detail) {
                if ($detail->room) {
                    $detail->room->update(['status' => $request->room_status]);
                }
            }

            // Kiểm tra xem tất cả phòng đã check-out chưa
            $totalDetails = $booking->details()->count();
            $checkedOutCount = $booking->details()->where('status', 'checked_out')->count();
            
            // Cập nhật trạng thái booking order
            $newStatus = ($checkedOutCount >= $totalDetails) ? 'checked_out' : 'partially_checked_out';
            $booking->update([
                'status' => $newStatus,
                'staff_id' => Auth::id(),
                'notes' => $request->notes ?? $booking->notes,
            ]);

            DB::commit();

            // Refresh booking với đầy đủ thông tin
            $booking->refresh();
            $booking->load(['guest', 'details.room', 'invoices.invoiceItems']);

            return response()->json([
                'success' => true,
                'message' => 'Check-out thành công.',
                'data' => $booking,
                'damage_summary' => [
                    'total_damage_fee' => $totalDamageFee,
                    'items' => $damageItems,
                ],
                'invoice' => $invoice->fresh(['invoiceItems']),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('CheckInOutController@checkOut failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi check-out: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get supplies for checkout - lấy danh sách vật tư để ghi nhận thiệt hại
     */
    public function getSuppliesForCheckout(Request $request, string $id): JsonResponse
    {
        try {
            $booking = BookingOrder::with(['details.room.property'])->findOrFail($id);

            // Lấy property_id từ booking
            $propertyIds = $booking->details->map(function ($detail) {
                return $detail->room->property_id ?? null;
            })->filter()->unique()->values()->toArray();

            // Lấy room_ids từ booking
            $roomIds = $booking->details->pluck('room_id')->toArray();

            // Lấy supplies theo room hoặc property
            $supplies = Supply::where('status', 'active')
                ->where(function ($query) use ($roomIds, $propertyIds) {
                    // Supplies thuộc về các phòng trong booking
                    $query->whereIn('room_id', $roomIds);
                    
                    // Hoặc supplies không thuộc phòng cụ thể nào (supplies chung)
                    if (!empty($propertyIds)) {
                        $query->orWhereNull('room_id');
                    }
                })
                ->orderBy('category')
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'category', 'unit', 'unit_price', 'current_stock']);

            return response()->json([
                'success' => true,
                'data' => $supplies,
            ]);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@getSuppliesForCheckout failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách vật tư.',
            ], 500);
        }
    }

    /**
     * Preview checkout - xem trước hóa đơn checkout với thiệt hại
     */
    public function previewCheckout(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'damaged_supplies' => 'nullable|array',
                'damaged_supplies.*.supply_id' => 'required|exists:supplies,id',
                'damaged_supplies.*.quantity' => 'required|integer|min:1',
                'damaged_supplies.*.unit_price' => 'nullable|numeric|min:0',
                'additional_services' => 'nullable|array',
                'additional_services.*.service_id' => 'required|exists:services,id',
                'additional_services.*.quantity' => 'required|integer|min:1',
            ]);

            $booking = BookingOrder::with(['details', 'invoices.invoiceItems'])->findOrFail($id);

            // Tính toán chi phí hiện có
            $existingInvoice = $booking->invoices()->first();
            $existingTotal = $existingInvoice ? $existingInvoice->total_amount : $booking->total_amount;
            $paidAmount = $existingInvoice ? $existingInvoice->paid_amount : ($booking->deposit_amount ?? 0);

            // Tính chi phí thiệt hại
            $damageItems = [];
            $totalDamageFee = 0;
            if ($request->has('damaged_supplies') && !empty($request->damaged_supplies)) {
                foreach ($request->damaged_supplies as $damagedItem) {
                    $supply = Supply::find($damagedItem['supply_id']);
                    if ($supply) {
                        $quantity = (int) $damagedItem['quantity'];
                        $unitPrice = $damagedItem['unit_price'] ?? $supply->unit_price;
                        $totalLine = $quantity * $unitPrice;

                        $damageItems[] = [
                            'supply_id' => $supply->id,
                            'name' => $supply->name,
                            'category' => $supply->category,
                            'quantity' => $quantity,
                            'unit' => $supply->unit,
                            'unit_price' => $unitPrice,
                            'total' => $totalLine,
                        ];

                        $totalDamageFee += $totalLine;
                    }
                }
            }

            // Tính chi phí dịch vụ phát sinh
            $serviceItems = [];
            $totalServiceFee = 0;
            if ($request->has('additional_services') && !empty($request->additional_services)) {
                foreach ($request->additional_services as $serviceData) {
                    $service = Service::find($serviceData['service_id']);
                    if ($service) {
                        $quantity = (int) $serviceData['quantity'];
                        $unitPrice = $service->unit_price;
                        $totalLine = $quantity * $unitPrice;

                        $serviceItems[] = [
                            'service_id' => $service->id,
                            'name' => $service->name,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total' => $totalLine,
                        ];

                        $totalServiceFee += $totalLine;
                    }
                }
            }

            // Tổng hợp
            $grandTotal = $existingTotal + $totalDamageFee + $totalServiceFee;
            $remainingAmount = $grandTotal - $paidAmount;

            return response()->json([
                'success' => true,
                'preview' => [
                    'existing_total' => $existingTotal,
                    'paid_amount' => $paidAmount,
                    'damage_items' => $damageItems,
                    'total_damage_fee' => $totalDamageFee,
                    'service_items' => $serviceItems,
                    'total_service_fee' => $totalServiceFee,
                    'grand_total' => $grandTotal,
                    'remaining_amount' => $remainingAmount,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('CheckInOutController@previewCheckout failed', [
                'booking_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo bản xem trước.',
            ], 500);
        }
    }

    /**
     * Store identity image to PRIVATE S3 bucket (s3_private disk)
     */
    private function storeIdentityImage($file): string
    {
        try {
            $extension = $file->getClientOriginalExtension();
            $filename = Str::uuid() . '.' . $extension;

            // Lưu vào bucket private, KHÔNG public
            $path = Storage::disk('s3_private')->putFileAs('identity_images', $file, $filename);

            if (!$path) {
                throw new \Exception('File không được lưu lên S3 (private).');
            }

            // DB chỉ lưu key/path; khi cần xem sẽ tạo temporaryUrl
            return $path;
        } catch (\Exception $e) {
            Log::error('CheckInOutController@storeIdentityImage failed (S3 private)', [
                'error' => $e->getMessage(),
            ]);
            throw new \Exception('Lỗi khi tải file giấy tờ tùy thân lên S3 (private): ' . $e->getMessage());
        }
    }
}

