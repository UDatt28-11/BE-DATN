<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Http\Resources\EmailTemplateResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EmailTemplateController extends Controller
{
    use AuthorizesRequests;

    private const DEFAULT_PER_PAGE = 15;

    /**
     * Display a listing of email templates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'language' => 'sometimes|string|max:10',
                'is_active' => 'sometimes|boolean',
                'search' => 'sometimes|string|max:255',
                'sort_by' => 'sometimes|string|in:id,name,language,created_at,updated_at',
                'sort_order' => 'sometimes|string|in:asc,desc',
                'page' => 'sometimes|integer|min:1',
                'per_page' => 'sometimes|integer|min:1|max:100',
            ], [
                'per_page.max' => 'Số lượng bản ghi mỗi trang không được vượt quá 100.',
            ]);

            $perPage = (int) ($request->get('per_page', self::DEFAULT_PER_PAGE));
            $query = EmailTemplate::query();

            // Filter by name
            if ($request->has('name')) {
                $query->where('name', $request->name);
            }

            // Filter by language
            if ($request->has('language')) {
                $query->where('language', $request->language);
            }

            // Filter by is_active
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search
            if ($request->has('search') && !empty($request->search)) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('subject', 'like', '%' . $request->search . '%');
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Paginate
            $templates = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => EmailTemplateResource::collection($templates),
                'meta' => [
                    'pagination' => [
                        'current_page' => $templates->currentPage(),
                        'per_page' => $templates->perPage(),
                        'total' => $templates->total(),
                        'last_page' => $templates->lastPage(),
                    ],
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('EmailTemplateController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy danh sách template email.',
            ], 500);
        }
    }

    /**
     * Store a newly created email template
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'required|string|max:255|unique:email_templates,name',
                'subject' => 'required|string|max:500',
                'body' => 'required|string',
                'language' => 'required|string|max:10|in:vi,en',
                'variables' => 'sometimes|array',
                'is_active' => 'sometimes|boolean',
            ], [
                'name.required' => 'Vui lòng nhập tên template.',
                'name.unique' => 'Tên template đã tồn tại.',
                'subject.required' => 'Vui lòng nhập tiêu đề email.',
                'body.required' => 'Vui lòng nhập nội dung email.',
                'language.required' => 'Vui lòng chọn ngôn ngữ.',
                'language.in' => 'Ngôn ngữ không hợp lệ. Chỉ chấp nhận: vi, en.',
            ]);

            if (!isset($validatedData['is_active'])) {
                $validatedData['is_active'] = true;
            }

            $template = EmailTemplate::create($validatedData);

            Log::info('EmailTemplate created', [
                'template_id' => $template->id,
                'name' => $template->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Tạo template email thành công',
                'data' => new EmailTemplateResource($template),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('EmailTemplateController@store failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi tạo template email.',
            ], 500);
        }
    }

    /**
     * Display the specified email template
     */
    public function show(EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => new EmailTemplateResource($emailTemplate),
            ]);
        } catch (\Exception $e) {
            Log::error('EmailTemplateController@show failed', [
                'template_id' => $emailTemplate->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy thông tin template email.',
            ], 500);
        }
    }

    /**
     * Update the specified email template
     */
    public function update(Request $request, EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'name' => 'sometimes|string|max:255|unique:email_templates,name,' . $emailTemplate->id,
                'subject' => 'sometimes|string|max:500',
                'body' => 'sometimes|string',
                'language' => 'sometimes|string|max:10|in:vi,en',
                'variables' => 'sometimes|array',
                'is_active' => 'sometimes|boolean',
            ], [
                'name.unique' => 'Tên template đã tồn tại.',
                'language.in' => 'Ngôn ngữ không hợp lệ. Chỉ chấp nhận: vi, en.',
            ]);

            $emailTemplate->update($validatedData);

            Log::info('EmailTemplate updated', [
                'template_id' => $emailTemplate->id,
                'name' => $emailTemplate->name,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật template email thành công',
                'data' => new EmailTemplateResource($emailTemplate),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('EmailTemplateController@update failed', [
                'template_id' => $emailTemplate->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật template email.',
            ], 500);
        }
    }

    /**
     * Remove the specified email template
     */
    public function destroy(EmailTemplate $emailTemplate): JsonResponse
    {
        try {
            $templateId = $emailTemplate->id;
            $templateName = $emailTemplate->name;

            $emailTemplate->delete();

            Log::info('EmailTemplate deleted', [
                'template_id' => $templateId,
                'name' => $templateName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Xóa template email thành công',
            ], 200);
        } catch (\Exception $e) {
            Log::error('EmailTemplateController@destroy failed', [
                'template_id' => $emailTemplate->id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xóa template email.',
            ], 500);
        }
    }
}

