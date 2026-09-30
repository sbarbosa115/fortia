<?php

declare(strict_types=1);

namespace App\Shared\UI\Http\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serves the two React apps from one Symfony project: the operator console under /console, and the respondent app
 * on the public routes of PRD §9.2 (whose URLs are already shared, §16.1). The React router takes it from there.
 */
final class SpaController extends AbstractController
{
    /**
     * @param array<string, mixed> $clientConfig public configuration for the apps (no secrets)
     */
    public function __construct(
        private readonly array $clientConfig,
        private readonly string $frontendUrl,
        private readonly string $adminFrontendUrl,
        private readonly string $appUrl,
    ) {
    }

    #[Route('/console', name: 'console_home', methods: ['GET'])]
    #[Route('/console/{path}', name: 'console', requirements: ['path' => '.+'], methods: ['GET'])]
    public function console(): Response
    {
        return $this->render('spa/console.html.twig', ['config' => $this->config()]);
    }

    #[Route('/', name: 'respondent_home', methods: ['GET'])]
    #[Route('/q/{path}', name: 'respondent_q', requirements: ['path' => '.+'], methods: ['GET'])]
    #[Route('/f/{path}', name: 'respondent_f', requirements: ['path' => '.+'], methods: ['GET'])]
    #[Route('/a/{path}', name: 'respondent_a', requirements: ['path' => '.+'], methods: ['GET'])]
    #[Route('/session/{path}', name: 'respondent_session', requirements: ['path' => '.+'], methods: ['GET'])]
    #[Route('/results', name: 'respondent_results', methods: ['GET'])]
    #[Route('/privacy', name: 'respondent_privacy', methods: ['GET'])]
    #[Route('/tiktok', name: 'respondent_tiktok', methods: ['GET'])]
    #[Route('/internal/qa/{path}', name: 'respondent_qa', requirements: ['path' => '.*'], methods: ['GET'])]
    public function respondent(): Response
    {
        return $this->render('spa/respondent.html.twig', ['config' => $this->config()]);
    }

    /** Legacy "/{id}" links redirect to /q/{id} in the app (PRD §9.2); lowest priority of all routes. */
    #[Route('/{id}', name: 'respondent_legacy', requirements: ['id' => '[A-Za-z0-9-]+'], methods: ['GET'], priority: -100)]
    public function legacy(): Response
    {
        return $this->respondent();
    }

    /** @return array<string, mixed> */
    private function config(): array
    {
        $config = $this->clientConfig;
        $config['googleSignInEnabled'] = '' !== (string) ($config['googleSignInClientId'] ?? '');
        unset($config['googleSignInClientId']);

        return $config + [
            'apiUrl' => rtrim($this->appUrl, '/').'/api/v1',
            'frontendUrl' => rtrim($this->frontendUrl, '/'),
            'consoleUrl' => rtrim($this->adminFrontendUrl, '/'),
        ];
    }
}
