<?php

namespace App\Identity\UI\Http\Controller;

use App\Billing\Application\Features;
use App\Billing\Application\PlanGate;
use App\Identity\Application\Command\ChangeSettings;
use App\Identity\Application\Query\AccountQueries;
use App\Identity\Domain\Error\CustomerNotFound;
use App\Identity\Domain\Model\SettingsChange;
use App\Identity\UI\Http\Input\SettingsInput;
use App\Identity\UI\Http\Output\CustomerSettingsOutput;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\TooManyRequests;
use App\Shared\UI\Http\Request\Payload;
use App\Shared\UI\Http\Response\ApiResponse;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * PRD §8.3 an account's settings: public to read (the respondent app reads the language, tracking ids and max_files,
 * §9.9, §9.15, §9.16; rate limited, D24), AG to change.
 */
#[OA\Tag(name: 'Account')]
final class SettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AccountQueries $accounts,
        private readonly PlanGate $gate,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    #[Route('/customer/{customer_id}/settings', name: 'api_settings_get', requirements: ['customer_id' => '[A-Za-z0-9]{1,16}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'CustomerSettings', content: new Model(type: CustomerSettingsOutput::class))]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function get(string $customer_id, Request $request): JsonResponse
    {
        if (!$this->publicApiLimiter->create('settings|'.$request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }
        $account = $this->accounts->find($customer_id) ?? throw new CustomerNotFound();

        return ApiResponse::ok(CustomerSettingsOutput::of($account['settings']));
    }

    /**
     * AG; a non-Admin only on their own account (another account is 404). Cap(profile) unless only the language
     * changes; ProfileEdited counts "profile" (§7.2).
     */
    #[Route('/customer/{customer_id}/settings', name: 'api_settings_patch', requirements: ['customer_id' => '[A-Za-z0-9]{1,16}'], methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'CustomerSettings after the change', content: new Model(type: CustomerSettingsOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    #[OA\Response(response: 429, description: 'PLAN_LIMIT_REACHED')]
    public function patch(string $customer_id, Caller $caller, #[Payload(allowExtraFields: false)] SettingsInput $input): JsonResponse
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
        if (!$caller->owns($customer_id)) {
            throw new CustomerNotFound();
        }
        $fields = $input->provided();
        if (!SettingsChange::of($fields)->onlyLanguage()) {
            $this->gate->capacity($caller, Features::PROFILE);
        }

        /** @var array<string, mixed> $settings */
        $settings = $this->commands->dispatch(new ChangeSettings($customer_id, $fields));

        return ApiResponse::ok(CustomerSettingsOutput::of($settings), 'Settings saved.');
    }
}
