<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\OutboxEvent;
use App\Domain\OutboxEventStatus;
use App\Domain\OrderState;
use App\Repository\OrderNotificationRepository;
use App\Repository\OrderRepository;
use App\Repository\OutboxRepository;
use App\Service\OrderService;
use App\Service\OutboxWorkerService;
use App\Service\PaymentService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class OutboxWorkerServiceTest extends TestCase
{
    private OrderService $orderService;
    private PaymentService $paymentService;
    private OutboxWorkerService $outboxWorkerService;
    private OutboxRepository $outboxRepository;
    private OrderNotificationRepository $notificationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());

        $orderRepository = new OrderRepository($this->connection);
        $this->outboxRepository = new OutboxRepository($this->connection);
        $this->notificationRepository = new OrderNotificationRepository($this->connection);

        $this->orderService = new OrderService($this->connection, $orderRepository);
        $this->paymentService = new PaymentService(
            $this->connection,
            $orderRepository,
            $this->outboxRepository,
            $logger
        );
        $this->outboxWorkerService = new OutboxWorkerService(
            $this->connection,
            $this->outboxRepository,
            $this->notificationRepository,
            $this->redis,
            $logger
        );
    }

    public function testOutboxWorkerIsIdempotent(): void
    {
        // Create order and process payment
        $order = $this->orderService->createOrder(100.00);
        $orderId = $order->getId();
        $this->paymentService->setSeed(1);
        $this->paymentService->processPayment($orderId);

        // Get the outbox event
        $event = $this->outboxRepository->findNextPending();
        $this->assertNotNull($event);
        $eventId = $event->getId();

        // Process event first time
        $processedEvent = $this->outboxWorkerService->processNextEvent();
        $this->assertNotNull($processedEvent);
        $this->assertEquals(OutboxEventStatus::PROCESSED, $processedEvent->getStatus());

        // Verify notification was created
        $this->assertTrue($this->notificationRepository->existsForOutboxEvent($eventId));

        // Verify Redis queue has message
        $queueLength = $this->redis->llen('order_notifications');
        $this->assertEquals(1, $queueLength);

        // Process same event again (should be idempotent)
        // First, manually create a new pending event to simulate retry scenario
        // In real scenario, we'd check if notification exists before processing
        $this->connection->update(
            'outbox_events',
            ['status' => OutboxEventStatus::PENDING->value],
            ['id' => $eventId]
        );

        // Process again - should detect existing notification and skip
        $retryEvent = $this->outboxRepository->findNextPending();
        $this->assertNotNull($retryEvent);
        $this->assertEquals($eventId, $retryEvent->getId());

        $processedEvent2 = $this->outboxWorkerService->processNextEvent();
        $this->assertNotNull($processedEvent2);
        $this->assertEquals(OutboxEventStatus::PROCESSED, $processedEvent2->getStatus());

        // Verify only one notification exists (idempotent)
        $notificationCount = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM order_notifications WHERE outbox_event_id = ?',
            [$eventId]
        );
        $this->assertEquals(1, (int) $notificationCount);

        // Verify Redis queue still has only one message
        $queueLength2 = $this->redis->llen('order_notifications');
        $this->assertEquals(1, $queueLength2);
    }

    public function testOutboxWorkerProcessesEventsInOrder(): void
    {
        // Create multiple orders and process payments
        $order1 = $this->orderService->createOrder(100.00);
        $order2 = $this->orderService->createOrder(200.00);
        $order3 = $this->orderService->createOrder(300.00);

        $this->paymentService->setSeed(1);
        $this->paymentService->processPayment($order1->getId());
        $this->paymentService->setSeed(2);
        $this->paymentService->processPayment($order2->getId());
        $this->paymentService->setSeed(3);
        $this->paymentService->processPayment($order3->getId());

        // Process events in order
        $event1 = $this->outboxWorkerService->processNextEvent();
        $this->assertNotNull($event1);
        $this->assertEquals($order1->getId(), $event1->getAggregateId());

        $event2 = $this->outboxWorkerService->processNextEvent();
        $this->assertNotNull($event2);
        $this->assertEquals($order2->getId(), $event2->getAggregateId());

        $event3 = $this->outboxWorkerService->processNextEvent();
        $this->assertNotNull($event3);
        $this->assertEquals($order3->getId(), $event3->getAggregateId());

        // No more events
        $event4 = $this->outboxWorkerService->processNextEvent();
        $this->assertNull($event4);
    }

    public function testOutboxWorkerReturnsNullWhenNoPendingEvents(): void
    {
        $event = $this->outboxWorkerService->processNextEvent();
        $this->assertNull($event);
    }
}

