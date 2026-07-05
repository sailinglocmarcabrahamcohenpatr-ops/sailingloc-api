<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ApiExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 10],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();

        if ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
        } else {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        $message = match ($statusCode) {
            Response::HTTP_NOT_FOUND => 'Route introuvable.',
            Response::HTTP_METHOD_NOT_ALLOWED => 'Méthode HTTP non autorisée.',
            Response::HTTP_UNAUTHORIZED => 'Authentification requise.',
            Response::HTTP_FORBIDDEN => 'Accès refusé.',
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_UNPROCESSABLE_ENTITY => $exception->getMessage() ?: 'Une erreur est survenue.',
            default => 'Une erreur est survenue.',
        };

        $event->setResponse(new JsonResponse(
            ['status' => $statusCode, 'message' => $message],
            $statusCode
        ));
    }
}
