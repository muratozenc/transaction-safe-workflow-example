<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Order;
use App\Domain\OrderState;
use App\Domain\OutboxEvent;
use App\Repository\OrderRepository;
use App\Repository\OutboxRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;

final class PaymentService
{
    private int $seed = 0;

    public function __construct(
        private readonly Connection $connection,
        private readonly OrderRepository $orderRepository,
        private readonly OutboxRepository $outboxRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function setSeed(int $seed): void
    {
        $this->seed = $seed;
    }

    public function processPayment(int $orderId): void
    {
        $this->connection->beginTransaction();
        try {
            $order = $this->orderRepository->findById($orderId);
            if ($order === null) {
                throw new \DomainException("Order {$orderId} not found");
            }

            if ($order->getState() !== OrderState::PENDING_PAYMENT) {
                throw new \DomainException("Order {$orderId} is not in PENDING_PAYMENT state");
            }

            $paymentSucceeded = $this->simulatePaymentGateway($order);

            if ($paymentSucceeded) {
                $order->markAsPaid();
                $eventType = 'ORDER_PAID';
            } else {
                $order->markAsPaymentFailed();
                $eventType = 'PAYMENT_FAILED';
            }

            $this->orderRepository->update($order);

            $outboxEvent = OutboxEvent::create(
                $orderId,
                $eventType,
                [
                    'order_id' => $orderId,
                    'state' => $order->getState()->value,
                    'total_amount' => $order->getTotalAmount(),
                ]
            );
            $this->outboxRepository->create($outboxEvent);

            $this->connection->commit();
            $this->logger->info("Payment processed for order {$orderId}: {$eventType}");
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->logger->error("Payment processing failed for order {$orderId}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function simulatePaymentGateway(Order $order): bool
    {
        if ($this->seed > 0) {
            mt_srand($this->seed + $order->getId());
        }

        return (mt_rand(1, 100) <= 70);
    }
}

