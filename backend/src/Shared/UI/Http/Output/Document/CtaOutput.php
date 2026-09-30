<?php

namespace App\Shared\UI\Http\Output\Document;

use OpenApi\Attributes as OA;

/** A call to action (PRD §6.6): {title ≤120, description ≤200, button: {text ≤50, url http(s)://}}. */
final class CtaOutput
{
    /** @param array{text: string, url: string} $button */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description,
        #[OA\Property(type: 'object', required: ['text', 'url'], properties: [new OA\Property(property: 'text', type: 'string'), new OA\Property(property: 'url', type: 'string')])]
        public readonly array $button,
    ) {
    }

    /** @param array<string, mixed>|null $c */
    public static function fromArray(?array $c): ?self
    {
        if (null === $c) {
            return null;
        }
        $button = \is_array($c['button'] ?? null) ? $c['button'] : [];

        return new self((string) ($c['title'] ?? ''), isset($c['description']) ? (string) $c['description'] : null, ['text' => (string) ($button['text'] ?? ''), 'url' => (string) ($button['url'] ?? '')]);
    }
}
