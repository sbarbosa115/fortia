<?php

namespace App\Identity\UI\Http\Output;

use OpenApi\Attributes as OA;

/** An account's CustomerSettings (PRD §6.1). */
final class CustomerSettingsOutput
{
    public function __construct(
        #[OA\Property(enum: ['es-CO', 'en-US'])]
        public readonly string $language,
        public readonly ?string $transcription_url,
        public readonly ?string $pixel_id,
        public readonly ?string $linkedin_partner_id,
        public readonly ?string $linkedin_conversion_id,
        public readonly ?string $google_ads_id,
        public readonly ?string $google_ads_conversion_label,
        #[OA\Property(minimum: 1, maximum: 20)]
        public readonly int $max_files,
    ) {
    }

    /** @param array<string, mixed> $settings */
    public static function of(array $settings): self
    {
        $text = static fn (string $key): ?string => isset($settings[$key]) && '' !== $settings[$key] ? (string) $settings[$key] : null;

        return new self(
            (string) ($settings['language'] ?? 'es-CO'),
            $text('transcription_url'),
            $text('pixel_id'),
            $text('linkedin_partner_id'),
            $text('linkedin_conversion_id'),
            $text('google_ads_id'),
            $text('google_ads_conversion_label'),
            (int) ($settings['max_files'] ?? 10),
        );
    }
}
