<?php

namespace App\Identity\UI\Http\Output;

use OpenApi\Attributes as OA;

/**
 * An account's system settings: its own SMTP server (null fields: the platform server is used) and whether it saved
 * an OpenAI key, and its own usage/analytics service (a null base URL: the platform's is used). Secrets are never
 * returned: only whether they are set, and the keys' last 4 characters.
 */
final class SystemSettingsOutput
{
    public function __construct(
        public readonly ?string $smtp_host,
        #[OA\Property(minimum: 1, maximum: 65535)]
        public readonly ?int $smtp_port,
        #[OA\Property(enum: ['tls', 'ssl', 'none'])]
        public readonly ?string $smtp_encryption,
        public readonly ?string $smtp_username,
        public readonly bool $smtp_password_set,
        public readonly ?string $smtp_from_email,
        public readonly ?string $smtp_from_name,
        public readonly bool $openai_api_key_set,
        public readonly ?string $openai_api_key_last4,
        public readonly ?string $analytics_base_url,
        public readonly bool $analytics_api_key_set,
        public readonly ?string $analytics_api_key_last4,
    ) {
    }

    /**
     * @param array{smtp_host: string|null, smtp_port: int|null, smtp_encryption: string|null, smtp_username: string|null, smtp_password_set: bool, smtp_from_email: string|null, smtp_from_name: string|null, openai_api_key_set: bool, openai_api_key_last4: string|null, analytics_base_url: string|null, analytics_api_key_set: bool, analytics_api_key_last4: string|null} $view
     */
    public static function of(array $view): self
    {
        return new self(
            $view['smtp_host'],
            $view['smtp_port'],
            $view['smtp_encryption'],
            $view['smtp_username'],
            $view['smtp_password_set'],
            $view['smtp_from_email'],
            $view['smtp_from_name'],
            $view['openai_api_key_set'],
            $view['openai_api_key_last4'],
            $view['analytics_base_url'],
            $view['analytics_api_key_set'],
            $view['analytics_api_key_last4'],
        );
    }
}
