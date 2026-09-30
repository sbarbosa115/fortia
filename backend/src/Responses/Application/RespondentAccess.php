<?php

namespace App\Responses\Application;

use App\Responses\Domain\Model\QuestionnaireSession;
use App\Shared\Application\Security\RespondentClaims;
use App\Shared\Domain\Error\Unauthenticated;

/**
 * Who may write a session (PRD §8.4): anyone with its id, except an assignation's session, which needs the
 * respondent token of that assignation — the member it belongs to, or any member of a follow-up's shared session.
 * Without it the answer is 401 (D7: never a silent 200).
 */
final class RespondentAccess
{
    public static function check(QuestionnaireSession $session, ?RespondentClaims $claims): void
    {
        $assignationsId = $session->assignationsId();
        if (null === $assignationsId) {
            return;
        }
        $member = $session->organizationUserId();
        if (null === $claims
            || $claims->assignationsId !== $assignationsId
            || (null !== $member && $claims->organizationUserId !== $member)) {
            throw new Unauthenticated('UNAUTHORIZED', 'This response belongs to an assignation: sign in to continue.');
        }
    }
}
