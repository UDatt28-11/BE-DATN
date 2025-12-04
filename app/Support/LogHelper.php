<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Helper class để filter sensitive data khi logging
 */
class LogHelper
{
    /**
     * Danh sách các fields nhạy cảm không nên log
     */
    private const SENSITIVE_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'api_key',
        'access_token',
        'refresh_token',
        'authorization',
        'bearer_token',
        'secret',
        'secret_key',
        'private_key',
        'credit_card',
        'card_number',
        'cvv',
        'ssn',
        'social_security_number',
        'id_card',
        'identity_number',
        'bank_account',
        'account_number',
        'pin',
        'otp',
        'verification_code',
    ];

    /**
     * Filter sensitive fields từ array
     *
     * @param array $data
     * @param array $additionalFields Additional sensitive fields to filter
     * @return array
     */
    public static function filterSensitiveData(array $data, array $additionalFields = []): array
    {
        $sensitiveFields = array_merge(self::SENSITIVE_FIELDS, $additionalFields);
        
        $filtered = [];
        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);
            
            // Check if key contains sensitive field
            $isSensitive = false;
            foreach ($sensitiveFields as $sensitiveField) {
                if (str_contains($keyLower, strtolower($sensitiveField))) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $filtered[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                // Recursively filter nested arrays
                $filtered[$key] = self::filterSensitiveData($value, $additionalFields);
            } else {
                $filtered[$key] = $value;
            }
        }
        
        return $filtered;
    }

    /**
     * Filter sensitive fields từ Request object
     *
     * @param Request $request
     * @param array $additionalFields Additional sensitive fields to filter
     * @return array
     */
    public static function filterRequest(Request $request, array $additionalFields = []): array
    {
        return self::filterSensitiveData($request->all(), $additionalFields);
    }

    /**
     * Filter sensitive fields từ query parameters
     *
     * @param Request $request
     * @param array $additionalFields Additional sensitive fields to filter
     * @return array
     */
    public static function filterQuery(Request $request, array $additionalFields = []): array
    {
        return self::filterSensitiveData($request->query(), $additionalFields);
    }
}

