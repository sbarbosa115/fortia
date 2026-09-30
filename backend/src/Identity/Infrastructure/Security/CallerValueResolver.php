<?php

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Repository\CustomerRepository;
use App\Identity\Domain\Repository\UserRepository;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Error\NotAllowed;
use App\Shared\Domain\Error\NotFound;
use App\Shared\Domain\Error\Unauthenticated;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * Resolves a controller's `Caller $caller` argument (401 without a console user; `?Caller` makes it optional).
 *
 * Impersonation (PRD §4.4): an Admin sending X-Assume-Customer-Id runs as that account's root user — groups
 * [Customer-Admin], root, no admin bypass. Anyone else sending it gets 403 ASSUME_NOT_ALLOWED; an unknown account
 * 404 ASSUMED_CUSTOMER_NOT_FOUND. /admin/* ignores the header. Writes made while assuming are logged (D18).
 */
final class CallerValueResolver implements ValueResolverInterface
{
    public const ASSUME_HEADER = 'X-Assume-Customer-Id';

    public function __construct(
        private readonly Security $security,
        private readonly UserRepository $users,
        private readonly CustomerRepository $customers,
        private readonly Connection $connection,
        private readonly Clock $clock,
    ) {
    }

    /** @return iterable<Caller|null> */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (Caller::class !== $argument->getType()) {
            return [];
        }
        $caller = $this->callerOf($request);
        if (null === $caller && !$argument->isNullable()) {
            throw new Unauthenticated('UNAUTHORIZED', 'No valid authentication.');
        }

        return [$caller];
    }

    public function callerOf(Request $request): ?Caller
    {
        if ($request->attributes->has('_caller')) {
            $cached = $request->attributes->get('_caller');

            return $cached instanceof Caller ? $cached : null;
        }
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
            return null;
        }
        $caller = new Caller($user->id, $user->email, $user->name, $user->customerId, $user->groups, $user->root);

        $assumed = trim((string) $request->headers->get(self::ASSUME_HEADER, ''));
        if ('' !== $assumed && !str_starts_with($request->getPathInfo(), '/api/v1/admin/')) {
            $caller = $this->assume($caller, $assumed, $request);
        }
        $request->attributes->set('_caller', $caller);

        return $caller;
    }

    private function assume(Caller $admin, string $customerId, Request $request): Caller
    {
        if (!$admin->isAdmin()) {
            throw new NotAllowed('ASSUME_NOT_ALLOWED', 'Only a super-admin can assume another account.');
        }
        $customer = $this->customers->find($customerId);
        $root = null === $customer ? null : $this->users->findRootOf($customerId);
        if (null === $customer || null === $root) {
            throw new NotFound('ASSUMED_CUSTOMER_NOT_FOUND', 'The account to assume does not exist.');
        }
        if (!$request->isMethodSafe()) {
            $this->connection->insert('impersonation_log', [
                'admin_email' => $admin->email,
                'customer_id' => $customerId,
                'method' => $request->getMethod(),
                'path' => substr($request->getPathInfo(), 0, 512),
                'occurred_at' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]);
        }

        return new Caller($root->id(), $root->email(), $root->name(), $customerId, [Caller::CUSTOMER_ADMIN], true, $admin->email);
    }
}
