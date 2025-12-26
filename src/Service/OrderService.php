<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Order;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;

final class OrderService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly OrderRepository $orderRepository
    ) {
    }

    public function createOrder(float $totalAmount): Order
    {
        $order = Order::create(0, $totalAmount);
        $id = $this->orderRepository->create($order);

        return new Order(
            $id,
            $order->getState(),
            $order->getTotalAmount(),
            $order->getCreatedAt(),
            $order->getUpdatedAt()
        );
    }

    public function cancelOrder(int $orderId): void
    {
        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            throw new \DomainException("Order {$orderId} not found");
        }

        $order->cancel();
        $this->orderRepository->update($order);
    }
}

