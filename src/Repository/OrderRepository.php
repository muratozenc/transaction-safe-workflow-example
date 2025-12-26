<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Order;
use App\Domain\OrderState;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class OrderRepository
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function create(Order $order): int
    {
        $this->connection->insert('orders', [
            'state' => $order->getState()->value,
            'total_amount' => $order->getTotalAmount(),
            'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $order->getUpdatedAt()->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function findById(int $id): ?Order
    {
        $row = $this->connection->fetchAssociative(
            'SELECT id, state, total_amount, created_at, updated_at FROM orders WHERE id = ?',
            [$id]
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function update(Order $order): void
    {
        $this->connection->update(
            'orders',
            [
                'state' => $order->getState()->value,
                'updated_at' => $order->getUpdatedAt()->format('Y-m-d H:i:s'),
            ],
            ['id' => $order->getId()]
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Order
    {
        return new Order(
            (int) $row['id'],
            OrderState::from($row['state']),
            (float) $row['total_amount'],
            new \DateTimeImmutable($row['created_at']),
            new \DateTimeImmutable($row['updated_at'])
        );
    }
}

