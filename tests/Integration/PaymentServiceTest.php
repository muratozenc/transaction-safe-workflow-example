<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\OrderState;
use App\Repository\OrderRepository;
use App\Repository\OutboxRepository;
use App\Service\OrderService;
use App\Service\PaymentService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final class PaymentServiceTest extends TestCase
{
    private OrderService $orderService;
    private PaymentService $paymentService;
    private OrderRepository $orderRepository;
    private OutboxRepository $outboxRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());

        $this->orderRepository = new OrderRepository($this->connection);
        $this->outboxRepository = new OutboxRepository($this->connection);
        $this->orderService = new OrderService($this->connection, $this->orderRepository);
        $this->paymentService = new PaymentService(
            $this->connection,
            $this->orderRepository,
            $this->outboxRepository,
            $logger
        );
    }

    public function testPaymentWritesOrderAndOutboxAtomically(): void
    {
        // Create order
        $order = $this->orderService->createOrder(100.00);
        $orderId = $order->getId();

        // Set seed for deterministic payment result
        $this->paymentService->setSeed(12345);

        // Process payment
        $this->paymentService->processPayment($orderId);

        // Verify order state was updated
        $updatedOrder = $this->orderRepository->findById($orderId);
        $this->assertNotNull($updatedOrder);
        $this->assertContains(
            $updatedOrder->getState(),
            [OrderState::PAID, OrderState::PAYMENT_FAILED]
        );

        // Verify outbox event was created
        $outboxEvent = $this->outboxRepository->findNextPending();
        $this->assertNotNull($outboxEvent);
        $this->assertEquals($orderId, $outboxEvent->getAggregateId());
        $this->assertContains(
            $outboxEvent->getType(),
            ['ORDER_PAID', 'PAYMENT_FAILED']
        );
    }

    public function testPaymentTransactionRollbackOnFailure(): void
    {
        // Create order
        $order = $this->orderService->createOrder(100.00);
        $orderId = $order->getId();

        // Try to pay an order that doesn't exist (simulate failure)
        $this->expectException(\DomainException::class);
        $this->paymentService->processPayment(99999);

        // Verify original order state unchanged
        $originalOrder = $this->orderRepository->findById($orderId);
        $this->assertNotNull($originalOrder);
        $this->assertEquals(OrderState::PENDING_PAYMENT, $originalOrder->getState());

        // Verify no outbox event was created
        $outboxEvent = $this->outboxRepository->findNextPending();
        $this->assertNull($outboxEvent);
    }

    public function testPaymentFailsForNonPendingOrder(): void
    {
        // Create and pay order
        $order = $this->orderService->createOrder(100.00);
        $orderId = $order->getId();
        $this->paymentService->setSeed(1);
        $this->paymentService->processPayment($orderId);

        // Try to pay again
        $this->expectException(\DomainException::class);
        $this->paymentService->processPayment($orderId);
    }
}

