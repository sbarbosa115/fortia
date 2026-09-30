<?php

namespace App\Branding\Application\Query;

use App\Branding\Domain\Repository\CustomerStylesRepository;

/** An account's brand for other contexts (the profile endpoint, the chat). */
final class StylesQueries
{
    public function __construct(private readonly CustomerStylesRepository $styles)
    {
    }

    /** @return array{website: string|null, styles: array<string, mixed>}|null */
    public function of(string $customerId): ?array
    {
        $styles = $this->styles->find($customerId);

        return null === $styles ? null : ['website' => $styles->website(), 'styles' => $styles->styles()];
    }
}
