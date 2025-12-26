<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\OutboxEvent;
use App\Domain\OutboxEventStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class OutboxRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function create(OutboxEvent $event): int
    {
        $this->connection->insert('outbox_events', [
            'aggregate_id' => $event->getAggregateId(),
            'type' => $event->getType(),
            'payload' => json_encode($event->getPayload(), JSON_THROW_ON_ERROR),
            'status' => $event->getStatus()->value,
            'created_at' => $event->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findNextPending(): ?OutboxEvent
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, aggregate_id, type, payload, status, created_at, processed_at 
             FROM outbox_events 
             WHERE status = ? 
             ORDER BY created_at ASC 
             LIMIT 1',
            [OutboxEventStatus::PENDING->value]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function update(OutboxEvent $event): void
    {
        $this->connection->update(
            'outbox_events',
            [
                'status' => $event->getStatus()->value,
                'processed_at' => $event->getProcessedAt()?->format('Y-m-d H:i:s'),
            ],
            ['id' => $event->getId()]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): OutboxEvent
    {
        return new OutboxEvent(
            (int) $row['id'],
            (int) $row['aggregate_id'],
            $row['type'],
            json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR),
            OutboxEventStatus::from($row['status']),
            new \DateTimeImmutable($row['created_at']),
            $row['processed_at'] ? new \DateTimeImmutable($row['processed_at']) : null
        );
    }
}

