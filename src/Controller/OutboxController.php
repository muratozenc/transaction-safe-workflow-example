<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\OutboxWorkerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class OutboxController
{
    public function __construct(
        private readonly OutboxWorkerService $outboxWorkerService
    ) {
    }

    public function runOnce(Request $request, Response $response): Response
    {
        $event = $this->outboxWorkerService->processNextEvent();

        if ($event === null) {
            $response->getBody()->write(json_encode([
                'processed' => false,
                'message' => 'No pending events',
            ], JSON_THROW_ON_ERROR));
        } else {
            $response->getBody()->write(json_encode([
                'processed' => true,
                'event' => [
                    'id' => $event->getId(),
                    'aggregate_id' => $event->getAggregateId(),
                    'type' => $event->getType(),
                    'status' => $event->getStatus()->value,
                ],
            ], JSON_THROW_ON_ERROR));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }
}

