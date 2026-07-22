<?php
declare(strict_types=1);

namespace App\Infrastructure\Http\EventSubscriber;

use App\Application\PlayerStats\Exception\InvalidPaginationException;
use App\Application\PlayerStats\Exception\UnsupportedMetricException;
use App\Infrastructure\Http\Response\ErrorResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Throwable;

final readonly class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $response = match (true) {
            $exception instanceof UnsupportedMetricException,
            $exception instanceof InvalidPaginationException => new ErrorResponse(
                $exception->getMessage(),
                Response::HTTP_BAD_REQUEST
            ),
            
            $exception instanceof ValidationFailedException => new ErrorResponse(
                $this->formatValidationErrors($exception),
                Response::HTTP_BAD_REQUEST
            ),

            default => $this->handleUnexpectedException($exception),
        };

        $event->setResponse($response);
    }

    private function formatValidationErrors(ValidationFailedException $exception): string
    {
        $violations = $exception->getViolations();
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = $violation->getMessage();
        }

        return implode(', ', $errors);
    }

    private function handleUnexpectedException(Throwable $exception): ErrorResponse
    {
        $this->logger->error('Unexpected exception occurred', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return new ErrorResponse(
            'Internal server error',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}
