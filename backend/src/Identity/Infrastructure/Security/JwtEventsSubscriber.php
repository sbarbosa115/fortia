<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Shared\UI\Http\Response\ApiResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * The id token carries what PRD §8.1 says: customer_id, groups, email, name and root. A bad or expired token gets
 * the API's own 401 shape.
 */
final class JwtEventsSubscriber
{
    #[AsEventListener(event: Events::JWT_CREATED)]
    public function onCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof SecurityUser) {
            return;
        }
        $event->setData(array_merge($event->getData(), [
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'customer_id' => $user->customerId,
            'root' => $user->root ? 'true' : 'false',
            'groups' => $user->groups,
        ]));
    }

    #[AsEventListener(event: Events::JWT_INVALID)]
    #[AsEventListener(event: Events::JWT_EXPIRED)]
    #[AsEventListener(event: Events::AUTHENTICATION_FAILURE)]
    public function onFailure(AuthenticationFailureEvent $event): void
    {
        $event->setResponse(ApiResponse::error('UNAUTHORIZED', 'No valid authentication.', 401));
    }
}
