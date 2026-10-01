<?php

namespace App\Identity\UI\Http\Controller;

use App\Branding\Application\Query\StylesQueries;
use App\Identity\Application\Query\AccountQueries;
use App\Identity\Domain\Error\CustomerNotFound;
use App\Identity\UI\Http\Output\ProfileCustomerOutput;
use App\Identity\UI\Http\Output\ProfileOutput;
use App\Shared\Application\Security\Caller;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.2 GET /profile: the caller's account (language, website) and brand (logo, styles from Branding), with the
 * signed-in user's name and email. The website is the brand's, or the one given in onboarding (D15).
 */
#[OA\Tag(name: 'Account')]
final class ProfileController
{
    public function __construct(
        private readonly AccountQueries $accounts,
        private readonly StylesQueries $styles,
    ) {
    }

    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'The profile', content: new Model(type: ProfileOutput::class))]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    public function __invoke(Caller $caller): JsonResponse
    {
        $account = $this->accounts->find($caller->customerId) ?? throw new CustomerNotFound();
        $brand = $this->styles->of($caller->customerId);
        $styles = $brand['styles'] ?? null;
        $logo = \is_array($styles) && \is_string($styles['logoUrl'] ?? null) && '' !== $styles['logoUrl'] ? $styles['logoUrl'] : null;

        return ApiResponse::ok(new ProfileOutput(new ProfileCustomerOutput(
            $account['customer_id'],
            $caller->name,
            $caller->email,
            $account['language'],
            $logo,
            $brand['website'] ?? $account['website'],
            \is_array($styles) && [] !== $styles ? $styles : null,
        )));
    }
}
