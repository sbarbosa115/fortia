<?php

namespace App\Commerce\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** The e-commerce platform refused the OAuth code (PRD §8.6 callback, Appendix B). */
final class TokenExchangeFailed extends Rejected
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('TOKEN_EXCHANGE_FAILED', 'We could not connect your store. Please try again.', [], $previous);
    }
}
