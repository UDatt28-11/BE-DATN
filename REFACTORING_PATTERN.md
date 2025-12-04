# Pattern Refactoring cho Controllers

## Tổng quan

Pattern này được áp dụng từ BE-DATN-dat để cải thiện code quality và maintainability của các controllers trong BE1.

## Pattern Components

### 1. QueryService Pattern

Tách logic query phức tạp ra khỏi Controller vào Service class riêng.

**Lợi ích:**
- Controller gọn hơn, dễ đọc
- Logic query có thể tái sử dụng
- Dễ test và maintain
- Separation of concerns

**Cấu trúc:**
```
app/Services/{Model}/QueryService.php
```

**Ví dụ:**
```php
namespace App\Services\Invoice;

class QueryService
{
    public function index(array $q): array
    {
        // Logic query ở đây
        return [
            'data' => $items,
            'meta' => ['pagination' => [...]],
            'links' => ['next' => ..., 'prev' => ...],
        ];
    }
}
```

### 2. IndexRequest Pattern

Tạo Request class riêng cho index method với validation linh hoạt.

**Lợi ích:**
- Tránh lỗi 422 với empty strings từ URL
- Validation tập trung, dễ maintain
- Có thể prepare data trước khi validate

**Cấu trúc:**
```
app/Http/Requests/Admin/Index{Model}Request.php
```

**Ví dụ:**
```php
namespace App\Http\Requests\Admin;

class IndexInvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => 'sometimes|string',
            'search' => 'sometimes|string|max:255',
            // ...
        ];
    }

    protected function prepareForValidation(): void
    {
        // Clean empty strings
        $data = $this->all();
        foreach ($data as $key => $value) {
            if ($value === '' || $value === null) {
                unset($data[$key]);
            }
        }
        $this->merge($data);
    }
}
```

### 3. Controller Refactoring

Controller chỉ còn gọi Service và trả về response.

**Trước:**
```php
public function index(Request $request): JsonResponse
{
    // 100+ dòng validation và query logic
    $request->validate([...]);
    $query = Model::query();
    // ... nhiều filters
    $items = $query->paginate($perPage);
    return response()->json([...]);
}
```

**Sau:**
```php
public function index(IndexModelRequest $request, QueryService $service): JsonResponse
{
    try {
        $result = $service->index($request->query());
        return response()->json([
            'success' => true,
            ...$result,
        ]);
    } catch (\Exception $e) {
        // Error handling
    }
}
```

## Đã áp dụng cho

✅ **BookingOrderController** - `app/Services/BookingOrder/QueryService.php`
✅ **InvoiceController** - `app/Services/Invoice/QueryService.php`
✅ **UserController** - `app/Services/User/QueryService.php`

## Có thể áp dụng cho

- **RoomController** - Có nhiều filters (property_id, room_type_id, status, verification_status, search)
- **PropertyController** - Có filters (owner_id, status, verification_status, search)
- **ReviewController** - Có filters (status, rating, search)
- **PromotionController** - Có filters (status, search, date range)
- **SupplyController** - Có filters (status, search, stock level)
- **VoucherController** - Có filters (status, search, date range)

## Các controller đơn giản (có thể bỏ qua)

Các controller sau có logic đơn giản, không cần thiết phải refactor:
- EmailConfigController
- EmailTemplateController
- EmailLogController
- PaymentController
- PayoutController
- PriceRuleController
- SubscriptionController
- ServiceController
- ConversationController
- MessageController

## Cách áp dụng

1. **Tạo QueryService:**
   ```bash
   # Tạo file app/Services/{Model}/QueryService.php
   ```

2. **Tạo IndexRequest:**
   ```bash
   # Tạo file app/Http/Requests/Admin/Index{Model}Request.php
   ```

3. **Refactor Controller:**
   - Import QueryService và IndexRequest
   - Thay đổi method signature
   - Gọi service->index()
   - Trả về response

4. **Test:**
   - Kiểm tra các filters vẫn hoạt động
   - Kiểm tra pagination
   - Kiểm tra sorting
   - Kiểm tra error handling

## Best Practices

1. **QueryService:**
   - Luôn return array với structure: `['data' => ..., 'meta' => ..., 'links' => ...]`
   - Sử dụng type hints cho parameters
   - Handle empty/null values gracefully
   - Support multiple filter types (string, array, date range)

2. **IndexRequest:**
   - Clean empty strings trong `prepareForValidation()`
   - Sử dụng `sometimes` cho optional fields
   - Provide meaningful error messages

3. **Controller:**
   - Luôn có try-catch
   - Log errors với context
   - Return consistent response format
   - Use dependency injection cho QueryService

## Notes

- Pattern này phù hợp với controllers có filtering phức tạp
- Không cần áp dụng cho controllers đơn giản (chỉ list all)
- Có thể tạo base QueryService class nếu cần share common logic

