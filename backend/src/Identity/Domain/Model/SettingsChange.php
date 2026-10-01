<?php

namespace App\Identity\Domain\Model;

/**
 * A change to an account's settings (PRD §8.3 PATCH settings), already shape-checked: tracking ids that are empty or
 * null clear them, and a null max_files goes back to the default.
 */
final class SettingsChange
{
    public const TRACKING_IDS = ['pixel_id', 'linkedin_partner_id', 'linkedin_conversion_id', 'google_ads_id', 'google_ads_conversion_label'];

    /** @param array<string, mixed> $changes */
    private function __construct(private readonly array $changes)
    {
    }

    /** @param array<string, mixed> $provided the fields sent, by name */
    public static function of(array $provided): self
    {
        $changes = [];
        foreach ($provided as $field => $value) {
            if (\in_array($field, self::TRACKING_IDS, true) || 'transcription_url' === $field) {
                $value = \is_string($value) ? trim($value) : null;
                $value = '' === $value ? null : $value;
            } elseif ('max_files' === $field) {
                $value ??= Customer::DEFAULT_MAX_FILES;
            }
            $changes[$field] = $value;
        }

        return new self($changes);
    }

    /** @return array<string, mixed> */
    public function changes(): array
    {
        return $this->changes;
    }

    /** @return list<string> */
    public function fields(): array
    {
        return array_keys($this->changes);
    }
}
