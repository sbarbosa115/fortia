<?php

namespace App\Branding\UI\Http\Controller;

use App\Branding\Application\Query\StylesQueries;
use App\Branding\UI\Http\Output\StylesOutput;
use App\Shared\Domain\Error\Rejected;
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
 * PRD §8.5 GET /styles?customer_id=&questionnaire_id= · P: the brand the respondent app applies (§9.15). One of the
 * two is required (400 if both are missing); only customer_id is used. Only the read: the branding item adds the
 * styles job (POST /styles).
 */
#[OA\Tag(name: 'Styles')]
final class StylesController
{
    public function __construct(
        private readonly StylesQueries $styles,
        #[Autowire(service: 'limiter.public_api')]
        private readonly RateLimiterFactoryInterface $publicApiLimiter,
    ) {
    }

    #[Route('/styles', name: 'api_styles_get', methods: ['GET'])]
    #[OA\Parameter(name: 'customer_id', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'questionnaire_id', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'The styles, or null', content: new Model(type: StylesOutput::class))]
    #[OA\Response(response: 400, description: 'INVALID_REQUEST (neither customer_id nor questionnaire_id)')]
    #[OA\Response(response: 429, description: 'TOO_MANY_ATTEMPTS')]
    public function __invoke(Request $request): JsonResponse
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

        return ApiResponse::ok(new StylesOutput(null === $found ? null : $found['styles']));
    }
}
