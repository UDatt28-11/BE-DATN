<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EmailConfigController extends Controller
{
    use AuthorizesRequests;

    /**
     * Get all email configurations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $configs = EmailConfig::all()->map(function ($config) {
                return [
                    'key' => $config->key,
                    'value' => $config->value,
                    'description' => $config->description,
                    'is_encrypted' => $config->is_encrypted,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $configs,
            ]);
        } catch (\Exception $e) {
            Log::error('EmailConfigController@index failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy cấu hình email.',
            ], 500);
        }
    }

    /**
     * Update email configurations
     */
    public function update(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'configs' => 'required|array',
                'configs.*.key' => 'required|string',
                'configs.*.value' => 'required|string',
                'configs.*.description' => 'sometimes|string|nullable',
                'configs.*.is_encrypted' => 'sometimes|boolean',
            ], [
                'configs.required' => 'Vui lòng cung cấp cấu hình.',
                'configs.*.key.required' => 'Key là bắt buộc.',
                'configs.*.value.required' => 'Value là bắt buộc.',
            ]);

            $updated = [];
            foreach ($validatedData['configs'] as $config) {
                $emailConfig = EmailConfig::updateOrCreate(
                    ['key' => $config['key']],
                    [
                        'value' => $config['value'],
                        'description' => $config['description'] ?? null,
                        'is_encrypted' => $config['is_encrypted'] ?? false,
                    ]
                );
                $updated[] = $emailConfig->key;
            }

            Log::info('EmailConfig updated', [
                'updated_keys' => $updated,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật cấu hình email thành công',
                'data' => [
                    'updated_keys' => $updated,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('EmailConfigController@update failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật cấu hình email.',
            ], 500);
        }
    }

    /**
     * Get SMTP configuration
     */
    public function getSmtpConfig(): JsonResponse
    {
        try {
            $smtpKeys = [
                'SMTP_HOST',
                'SMTP_PORT',
                'SMTP_USERNAME',
                'SMTP_PASSWORD',
                'SMTP_ENCRYPTION',
                'SMTP_FROM_ADDRESS',
                'SMTP_FROM_NAME',
            ];

            $configs = [];
            foreach ($smtpKeys as $key) {
                $config = EmailConfig::where('key', $key)->first();
                $configs[$key] = [
                    'value' => $config ? $config->value : '',
                    'description' => $config ? $config->description : null,
                    'is_encrypted' => $config ? $config->is_encrypted : false,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $configs,
            ]);
        } catch (\Exception $e) {
            Log::error('EmailConfigController@getSmtpConfig failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi lấy cấu hình SMTP.',
            ], 500);
        }
    }

    /**
     * Update SMTP configuration
     */
    public function updateSmtpConfig(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'SMTP_HOST' => 'required|string',
                'SMTP_PORT' => 'required|integer|min:1|max:65535',
                'SMTP_USERNAME' => 'required|string',
                'SMTP_PASSWORD' => 'required|string',
                'SMTP_ENCRYPTION' => 'sometimes|string|in:tls,ssl',
                'SMTP_FROM_ADDRESS' => 'required|email',
                'SMTP_FROM_NAME' => 'required|string',
            ], [
                'SMTP_HOST.required' => 'Vui lòng nhập SMTP host.',
                'SMTP_PORT.required' => 'Vui lòng nhập SMTP port.',
                'SMTP_USERNAME.required' => 'Vui lòng nhập SMTP username.',
                'SMTP_PASSWORD.required' => 'Vui lòng nhập SMTP password.',
                'SMTP_FROM_ADDRESS.required' => 'Vui lòng nhập địa chỉ email gửi.',
                'SMTP_FROM_ADDRESS.email' => 'Địa chỉ email không hợp lệ.',
                'SMTP_FROM_NAME.required' => 'Vui lòng nhập tên người gửi.',
            ]);

            $descriptions = [
                'SMTP_HOST' => 'SMTP Server Host',
                'SMTP_PORT' => 'SMTP Server Port',
                'SMTP_USERNAME' => 'SMTP Username',
                'SMTP_PASSWORD' => 'SMTP Password (encrypted)',
                'SMTP_ENCRYPTION' => 'SMTP Encryption (tls/ssl)',
                'SMTP_FROM_ADDRESS' => 'Default From Email Address',
                'SMTP_FROM_NAME' => 'Default From Name',
            ];

            $updated = [];
            foreach ($validatedData as $key => $value) {
                EmailConfig::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value,
                        'description' => $descriptions[$key] ?? null,
                        'is_encrypted' => $key === 'SMTP_PASSWORD',
                    ]
                );
                $updated[] = $key;
            }

            Log::info('SMTP Config updated', [
                'updated_keys' => $updated,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật cấu hình SMTP thành công',
                'data' => [
                    'updated_keys' => $updated,
                ],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('EmailConfigController@updateSmtpConfig failed', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật cấu hình SMTP.',
            ], 500);
        }
    }
}

