<?php

namespace App\Commerce\UI\Http\Input;

use App\Commerce\Application\Job\ScrapeProductsJob;
use App\Commerce\Domain\StoreOrigin;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** PRD §8.6 POST /scrapers/products: url (the scheme is added if missing; the host must contain a dot), limit 1–30. */
final class ScrapeInput
{
    #[Assert\NotNull]
    #[Assert\Length(max: 2048)]
    public ?string $url = null;

    #[Assert\Range(min: ScrapeProductsJob::MIN, max: ScrapeProductsJob::MAX)]
    public ?int $limit = null;

    #[Assert\Callback]
    public function validateUrl(ExecutionContextInterface $context): void
    {
        if (null !== $this->url && null === StoreOrigin::of($this->url)) {
            $context->buildViolation('Please enter a valid store URL.')->atPath('url')->addViolation();
        }
    }

    /** The URL as given, with https:// when it had no scheme. */
    public function url(): string
    {
        $url = trim((string) $this->url);

        return 1 === preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? $url : 'https://'.$url;
    }

    public function limit(): int
    {
        return $this->limit ?? ScrapeProductsJob::DEFAULT;
    }
}
