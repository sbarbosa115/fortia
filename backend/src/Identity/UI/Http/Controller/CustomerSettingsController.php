<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Query\AccountQueries;
use App\Identity\Domain\Error\CustomerNotFound;
use App\Identity\UI\Http\Output\CustomerSettingsOutput;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.3 GET /customer/{customer_id}/settings · P: the respondent app reads the account's language, tracking ids
 * and max_files (§9.9, §9.15, §9.16). Only the read: the accounts item adds the PATCH (its SettingsController
 * replaces this class when it merges, with the same route name and output).
 */
#[OA\Tag(name: 'Account')]
final class CustomerSettingsController
{
    public function __construct(
        private readonly AccountQueries $accounts,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    #[Route('/customer/{customer_id}/settings', name: 'api_settings_get', requirements: ['customer_id' => '[A-Za-z0-9]{1,16}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'CustomerSettings', content: new Model(type: CustomerSettingsOutput::class))]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function __invoke(string $customer_id, Request $request): JsonResponse
    {
        if (!$this->publicApiLimiter->create('settings|'.$request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }
        $account = $this->accounts->find($customer_id) ?? throw new CustomerNotFound();

        return ApiResponse::ok(CustomerSettingsOutput::of($account['settings']));
    }
}
