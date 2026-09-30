<?php

namespace App\Shared\UI\Http\Security;

use App\Shared\UI\Http\Response\ApiResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/** A route that needs a console user, called without one: 401 UNAUTHORIZED. */
final class JsonAuthenticationEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return ApiResponse::error('UNAUTHORIZED', 'No valid authentication.', Response::HTTP_UNAUTHORIZED);
    }
}
