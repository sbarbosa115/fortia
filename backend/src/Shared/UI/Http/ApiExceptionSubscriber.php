<?php

namespace App\Shared\UI\Http;

use App\Shared\Domain\Error\DomainError;
use App\Shared\UI\Http\Response\ApiResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Turns anything thrown under /api into the error envelope of PRD §8.1: a DomainError by its kind, framework HTTP
 * errors by their status, and everything else as 500 INTERNAL_ERROR (logged, never with its message).
 */
final class ApiExceptionSubscriber
{
    private const HTTP_CODES = [
        400 => ['INVALID_REQUEST', 'The request is not valid.'],
        401 => ['UNAUTHORIZED', 'No valid authentication.'],
        403 => ['FORBIDDEN', 'Admin privileges are required.'],
        404 => ['NOT_FOUND', 'Not found.'],
        405 => ['METHOD_NOT_ALLOWED', 'Method not allowed.'],
        415 => ['INVALID_JSON', 'The body must be JSON.'],
        429 => ['TOO_MANY_ATTEMPTS', 'Too many attempts. Try again later.'],
    ];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: -10)]
    public function onException(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api/')) {
            return;
        }
        $error = $event->getThrowable();
        while ($error instanceof HandlerFailedException && null !== $error->getPrevious()) {
            $error = $error->getPrevious();
        }

        if ($error instanceof DomainError) {
            $status = ErrorStatus::of($error);
            if ($status >= 500) {
                $this->logger->error($error->getMessage(), ['exception' => $error]);
            }
            $event->setResponse(ApiResponse::error($error->errorCode(), $error->getMessage(), $status, $error->details()));

            return;
        }

        if ($error instanceof HttpExceptionInterface) {
            $status = $error->getStatusCode();
            [$code, $message] = self::HTTP_CODES[$status] ?? ['INTERNAL_ERROR', 'Something went wrong.'];
            $response = ApiResponse::error($code, $message, $status);
            $response->headers->add($error->getHeaders());
            $event->setResponse($response);

            return;
        }

        $this->logger->error($error->getMessage(), ['exception' => $error]);
        $event->setResponse(ApiResponse::error('INTERNAL_ERROR', 'Something went wrong.', 500));
    }
}
