<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use App\Shared\Application\Security\RespondentClaims;
use App\Shared\Application\Security\RespondentTokens;
use App\Shared\Domain\Clock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\StrictValidAt;
use Psr\Clock\ClockInterface;

/**
 * HS256 tokens signed with RESPONDENT_TOKEN_SECRET, valid for RESPONDENT_TOKEN_TTL_DAYS (D6). They start with "rt."
 * so the console's JWT authenticator can tell them apart and leave them to the respondent endpoints.
 */
final class JwtRespondentTokens implements RespondentTokens
{
    public const PREFIX = 'rt.';
    private const ISSUER = 'mappi-respondent';

    private readonly Configuration $config;

    public function __construct(
        string $secret,
        private readonly int $ttlDays,
        private readonly Clock $clock,
    ) {
        if (\strlen($secret) < 32) {
            $secret = str_pad($secret, 32, '.');
        }
        $this->config = Configuration::forSymmetricSigner(new Sha256(), InMemory::plainText($secret));
    }

    public function issue(string $assignationsId, string $organizationUserId, string $sessionId): string
    {
        $now = $this->clock->now();

        return self::PREFIX.$this->config->builder()
            ->issuedBy(self::ISSUER)
            ->issuedAt($now)
            ->expiresAt($now->modify(\sprintf('+%d days', $this->ttlDays)))
            ->withClaim('assignations_id', $assignationsId)
            ->withClaim('organization_user_id', $organizationUserId)
            ->withClaim('session_id', $sessionId)
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();
    }

    public function parse(string $token): ?RespondentClaims
    {
        if (!str_starts_with($token, self::PREFIX)) {
            return null;
        }
        try {
            $parsed = $this->config->parser()->parse(substr($token, \strlen(self::PREFIX)));
        } catch (\Throwable) {
            return null;
        }
        if (!$parsed instanceof Plain || !$parsed->hasBeenIssuedBy(self::ISSUER)) {
            return null;
        }
        $clock = new class($this->clock) implements ClockInterface {
            public function __construct(private readonly Clock $clock)
            {
            }

            public function now(): \DateTimeImmutable
            {
                return $this->clock->now();
            }
        };
        $valid = $this->config->validator()->validate(
            $parsed,
            new SignedWith($this->config->signer(), $this->config->verificationKey()),
            new StrictValidAt($clock),
        );
        if (!$valid) {
            return null;
        }
        $claims = $parsed->claims();
        $assignation = $claims->get('assignations_id');
        $member = $claims->get('organization_user_id');
        $session = $claims->get('session_id');
        if (!\is_string($assignation) || !\is_string($member) || !\is_string($session)) {
            return null;
        }

        return new RespondentClaims($assignation, $member, $session);
    }
}
