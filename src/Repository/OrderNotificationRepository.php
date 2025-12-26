<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class OrderNotificationRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function existsForOutboxEvent(int $outboxEventId): bool
    {
        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM order_notifications WHERE outbox_event_id = ?',
            [$outboxEventId]
        );

        return (int) $count > 0;
    }

    public function create(int $outboxEventId, int $orderId, string $notificationType, string $message): int
    {
        $this->connection->insert('order_notifications', [
            'outbox_event_id' => $outboxEventId,
            'order_id' => $orderId,
            'notification_type' => $notificationType,
            'message' => $message,
        ]);

        return (int) $this->connection->lastInsertId();
    }
}

