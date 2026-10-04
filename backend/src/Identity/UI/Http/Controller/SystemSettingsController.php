<?php

namespace App\Identity\UI\Http\Controller;

use App\Identity\Application\Command\ChangeSystemSettings;
use App\Identity\Application\Command\CheckSmtpServer;
use App\Identity\Application\Query\AccountQueries;
use App\Identity\Application\Query\SystemSettingsQueries;
use App\Identity\Domain\Error\CustomerNotFound;
use App\Identity\UI\Http\Input\SmtpCheckInput;
use App\Identity\UI\Http\Input\SystemSettingsInput;
use App\Identity\UI\Http\Output\SystemSettingsOutput;
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
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The System tab of /profile: an account's own SMTP server and OpenAI API key. Its console users read it; AG change
 * and check it. Another account's id is 404.
 */
#[OA\Tag(name: 'Account')]
final class SystemSettingsController
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly AccountQueries $accounts,
        private readonly SystemSettingsQueries $settings,
        #[Autowire(service: 'limiter.smtp_check')]
        private readonly RateLimiterFactoryInterface $smtpCheckLimiter,
    ) {
    }

    #[Route('/customer/{customer_id}/system-settings', name: 'api_system_settings_get', requirements: ['customer_id' => '[A-Za-z0-9]{1,16}'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'SystemSettings', content: new Model(type: SystemSettingsOutput::class))]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    public function get(string $customer_id, Caller $caller): JsonResponse
    {
        $this->ownAccount($caller, $customer_id);

        return ApiResponse::ok(SystemSettingsOutput::of($this->settings->view($customer_id)));
    }

    /** AG. */
    #[Route('/customer/{customer_id}/system-settings', name: 'api_system_settings_patch', requirements: ['customer_id' => '[A-Za-z0-9]{1,16}'], methods: ['PATCH'])]
    #[OA\Response(response: 200, description: 'SystemSettings after the change', content: new Model(type: SystemSettingsOutput::class))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    #[OA\Response(response: 422, description: 'INVALID_SMTP_SERVER')]
    public function patch(string $customer_id, Caller $caller, #[Payload(allowExtraFields: false)] SystemSettingsInput $input): JsonResponse
    {
        $this->mayChange($caller, $customer_id);
        $this->commands->dispatch(new ChangeSystemSettings($customer_id, $input->provided()));

        return ApiResponse::ok(SystemSettingsOutput::of($this->settings->view($customer_id)), 'Settings saved.');
    }

    /**
     * AG. Sends a test email through the form's server to the user who asked (rate limited). Nothing is saved.
     */
    #[Route('/customer/{customer_id}/system-settings/smtp-check', name: 'api_system_settings_smtp_check', requirements: ['customer_id' => '[A-Za-z0-9]{1,16}'], methods: ['POST'])]
    #[OA\Response(response: 200, description: 'The test email was accepted by the server')]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    #[OA\Response(response: 404, description: 'CUSTOMER_NOT_FOUND')]
    #[OA\Response(response: 422, description: 'INVALID_SMTP_SERVER')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    #[OA\Response(response: 502, description: 'SMTP_CHECK_FAILED (details.reason: connection, tls, authentication, blocked, refused)')]
    public function check(string $customer_id, Caller $caller, #[Payload(allowExtraFields: false)] SmtpCheckInput $input): JsonResponse
    {
        $this->mayChange($caller, $customer_id);
        if (!$this->smtpCheckLimiter->create($customer_id)->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many checks. Please try again in a few minutes.');
        }
        $to = $caller->realEmail ?? $caller->email;
        $this->commands->dispatch(new CheckSmtpServer($customer_id, $input->provided(), $to));

        return ApiResponse::ok(['sent_to' => $to], 'Test email sent.');
    }

    private function ownAccount(Caller $caller, string $customerId): void
    {
        if (!$caller->owns($customerId) || null === $this->accounts->find($customerId)) {
            throw new CustomerNotFound();
        }
    }

    private function mayChange(Caller $caller, string $customerId): void
    {
        $this->ownAccount($caller, $customerId);
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
    }
}
