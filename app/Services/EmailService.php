<?php

namespace App\Services;

use App\Models\EmailTemplate;
use App\Models\EmailLog;
use App\Models\EmailConfig;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class EmailService
{
    /**
     * Send email using template
     */
    public function sendTemplateEmail(
        string $templateName,
        string $recipientEmail,
        array $variables = [],
        string $language = 'vi',
        $related = null
    ): bool {
        try {
            $template = EmailTemplate::where('name', $templateName)
                ->where('language', $language)
                ->active()
                ->first();

            if (!$template) {
                Log::error('Email template not found', [
                    'template_name' => $templateName,
                    'language' => $language,
                ]);
                return false;
            }

            // Replace variables in subject and body
            $subject = $this->replaceVariables($template->subject, $variables);
            $body = $this->replaceVariables($template->body, $variables);

            // Create email log
            $emailLog = EmailLog::create([
                'template_id' => $template->id,
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
                'body' => $body,
                'status' => 'pending',
                'related_type' => $related ? get_class($related) : null,
                'related_id' => $related ? $related->id : null,
            ]);

            // Send email
            $sent = $this->sendEmail($recipientEmail, $subject, $body);

            // Update log
            if ($sent) {
                $emailLog->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
            } else {
                $emailLog->update([
                    'status' => 'failed',
                    'error_message' => 'Failed to send email',
                ]);
            }

            return $sent;
        } catch (Exception $e) {
            Log::error('EmailService@sendTemplateEmail failed', [
                'template_name' => $templateName,
                'recipient_email' => $recipientEmail,
                'error' => $e->getMessage(),
            ]);

            // Create failed log
            if (isset($emailLog)) {
                $emailLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            return false;
        }
    }

    /**
     * Send plain email
     */
    public function sendEmail(string $to, string $subject, string $body, string $contentType = 'html'): bool
    {
        try {
            // Get SMTP config from database
            $smtpConfig = $this->getSmtpConfig();

            if (!$smtpConfig) {
                Log::error('SMTP config not found');
                return false;
            }

            // Configure mail
            config([
                'mail.mailers.smtp.host' => $smtpConfig['SMTP_HOST'] ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => $smtpConfig['SMTP_PORT'] ?? config('mail.mailers.smtp.port'),
                'mail.mailers.smtp.username' => $smtpConfig['SMTP_USERNAME'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $smtpConfig['SMTP_PASSWORD'] ?? config('mail.mailers.smtp.password'),
                'mail.mailers.smtp.encryption' => $smtpConfig['SMTP_ENCRYPTION'] ?? config('mail.mailers.smtp.encryption'),
                'mail.from.address' => $smtpConfig['SMTP_FROM_ADDRESS'] ?? config('mail.from.address'),
                'mail.from.name' => $smtpConfig['SMTP_FROM_NAME'] ?? config('mail.from.name'),
            ]);

            // Send email using Mail facade
            Mail::html($body, function ($message) use ($to, $subject) {
                $message->to($to)
                    ->subject($subject);
            });

            return true;
        } catch (Exception $e) {
            Log::error('EmailService@sendEmail failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Replace variables in template
     */
    private function replaceVariables(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
            $template = str_replace('{' . $key . '}', $value, $template);
        }

        return $template;
    }

    /**
     * Get SMTP configuration from database
     */
    private function getSmtpConfig(): ?array
    {
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
            if ($config) {
                $configs[$key] = $config->value;
            }
        }

        return !empty($configs) ? $configs : null;
    }
}

