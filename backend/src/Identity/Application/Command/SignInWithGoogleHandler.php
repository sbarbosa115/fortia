<?php

namespace App\Identity\Application\Command;

use App\Identity\Application\AccountRegistrar;
use App\Identity\Application\GoogleSignIn;
use App\Identity\Application\GoogleSignInResult;
use App\Identity\Application\Port\GoogleIdentity;
use App\Identity\Application\Port\TokenIssuer;
use App\Identity\Domain\Error\GoogleSignInFailed;
use App\Identity\Domain\Error\ProviderNotConfigured;
use App\Identity\Domain\Event\UserSignedIn;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Bus\EventBus;
use App\Shared\Domain\Clock;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * PRD §13.1 "First Google login": creates the account (root, Customer-Admin, starter, onboarding pending, language
 * from Google); an existing password account with the same email is linked and the user asked to retry
 * (EMAIL_LINKED_RETRY_LOGIN); a linked user signs in.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class SignInWithGoogleHandler
{
    public function __construct(
        private readonly GoogleIdentity $google,
        private readonly GoogleSignIn $signIn,
        private readonly UserRepository $users,
        private readonly AccountRegistrar $registrar,
        private readonly TokenIssuer $tokens,
        private readonly EventBus $events,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(SignInWithGoogle $command): GoogleSignInResult
    {
        if (!$this->google->isConfigured()) {
            throw new ProviderNotConfigured();
        }
        $profile = $this->google->exchange($command->code, $command->codeVerifier, $this->signIn->redirectUri());
        $now = $this->clock->now();

        $user = $this->users->findByEmail($profile->email);
        if (null === $user) {
            $name = '' !== trim($profile->name) ? $profile->name : strstr($profile->email, '@', true);
            $user = $this->registrar->register($profile->email, (string) $name, $profile->accountLanguage(), 'google', 'google');
            $user->linkGoogle($profile->subject, $now);
        } elseif (null === $user->googleSubject()) {
            $user->linkGoogle($profile->subject, $now);

            return GoogleSignInResult::linked();
        } elseif ($user->googleSubject() !== $profile->subject) {
            throw new GoogleSignInFailed();
        }

        $user->recordSignIn($now);
        $this->events->publish(new UserSignedIn($user->customerId(), ['email' => $user->email()]));

        return GoogleSignInResult::signedIn($this->tokens->issue($user));
    }
}
