<?php

namespace App\Identity\Application\Query;

use App\Identity\Domain\Repository\SystemSettingsRepository;

/** What the System tab shows: the server without its password, and whether a key is saved (its last 4 only). */
final class SystemSettingsQueries
{
    public function __construct(private readonly SystemSettingsRepository $settings)
    {
    }

    /**
     * @return array{smtp_host: string|null, smtp_port: int|null, smtp_encryption: string|null, smtp_username: string|null, smtp_password_set: bool, smtp_from_email: string|null, smtp_from_name: string|null, openai_api_key_set: bool, openai_api_key_last4: string|null}
     */
    public function view(string $customerId): array
    {
        $settings = $this->settings->find($customerId);
        $smtp = $settings?->smtp();

        return [
            'smtp_host' => $smtp?->host,
            'smtp_port' => $smtp?->port,
            'smtp_encryption' => $smtp?->encryption,
            'smtp_username' => $smtp?->username,
            'smtp_password_set' => null !== $smtp?->sealedPassword,
            'smtp_from_email' => $smtp?->fromEmail,
            'smtp_from_name' => $smtp?->fromName,
            'openai_api_key_set' => null !== $settings?->sealedOpenAiKey(),
            'openai_api_key_last4' => $settings?->openAiKeyHint(),
        ];
    }
}
