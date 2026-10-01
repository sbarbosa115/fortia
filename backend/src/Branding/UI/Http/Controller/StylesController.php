<?php

namespace App\Branding\UI\Http\Controller;

use App\Branding\Application\Command\RequestStyles;
use App\Branding\Application\Query\StylesQueries;
use App\Branding\UI\Http\Input\StylesInput;
use App\Branding\UI\Http\Output\StylesOutput;
use App\Jobs\Application\Query\JobQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\Rejected;
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
 * PRD §8.5 styles. GET /styles is public: the brand the respondent app applies (§9.15). POST /styles starts the
 * styles job (§7.16) that the console's Customization screen polls (§10.13).
 */
#[OA\Tag(name: 'Styles')]
final class StylesController
{
    public function __construct(
        private readonly StylesQueries $styles,
        private readonly CommandBus $commands,
        private readonly JobQueries $jobs,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    /**
     * P. One of customer_id or questionnaire_id is required (400 if both are missing); only customer_id is used.
     * `website` is only filled in for a console user of that account (the Customization screen reads it back); an
     * anonymous caller always gets null.
     */
    #[Route('/styles', name: 'api_styles_get', methods: ['GET'])]
    #[OA\Parameter(name: 'customer_id', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'questionnaire_id', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'The styles, or null', content: new Model(type: StylesOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_REQUEST (neither customer_id nor questionnaire_id)')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function read(Request $request, ?Caller $caller): JsonResponse
    {
        if (!$this->publicApiLimiter->create('styles|'.$request->getClientIp())->consume()->isAccepted()) {
            throw new TooManyRequests('TOO_MANY_ATTEMPTS', 'Too many requests. Please try again in a minute.');
        }
        $customerId = trim((string) $request->query->get('customer_id', ''));
        $questionnaireId = trim((string) $request->query->get('questionnaire_id', ''));
        if ('' === $customerId && '' === $questionnaireId) {
            throw new Rejected('INVALID_REQUEST', 'customer_id or questionnaire_id is required.');
        }
        $found = '' === $customerId ? null : $this->styles->of($customerId);
        $ownWebsite = null !== $found && null !== $caller && $caller->owns($customerId) ? $found['website'] : null;

        return ApiResponse::ok(new StylesOutput(null === $found ? null : $found['styles'], $ownWebsite));
    }

    /**
     * AG. 202 {job} (job_type "styles", stages reading_website → designing_styles → saving).
     */
    #[Route('/styles', name: 'api_styles_update', methods: ['POST'])]
    #[OA\RequestBody(content: new Model(type: StylesInput::class))]
    #[OA\Response(response: 202, description: 'The styles job', content: new OA\JsonContent(ref: '#/components/schemas/JobEnvelopeOutput'))]
    #[OA\Response(response: 400, description: 'VALIDATION_ERROR')]
    #[OA\Response(response: 401, description: 'UNAUTHORIZED')]
    #[OA\Response(response: 403, description: 'FORBIDDEN')]
    public function update(Caller $caller, #[Payload(allowExtraFields: false)] StylesInput $input): JsonResponse
    {
        if (!$caller->inAdminGroups()) {
            throw new NotAllowed('FORBIDDEN', 'Admin privileges are required.');
        }
        $jobId = (string) $this->commands->dispatch(new RequestStyles($caller->customerId, $input->website(), $input->styles));

        return ApiResponse::accepted(['job' => $this->jobs->find($jobId)], 'Updating styles');
    }
}
