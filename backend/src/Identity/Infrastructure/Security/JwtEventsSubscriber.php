<?php

namespace App\Identity\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationFailureEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;

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
        // The API's error shape (ApiResponse::error), written out here so Infrastructure does not depend on UI.
        $event->setResponse(new JsonResponse(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'No valid authentication.']], 401));
    }
}
