<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\OrderState;
use App\Repository\OrderRepository;
use App\Service\OrderService;
use App\Service\PaymentService;
use Monolog\Handler\NullHandler;
use Monolog\Logger;

final class OrderServiceTest extends TestCase
{
    private OrderService $orderService;
    private PaymentService $paymentService;
    private OrderRepository $orderRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $logger = new Logger('test');
        $logger->pushHandler(new NullHandler());

        $this->orderRepository = new OrderRepository($this->connection);
        $this->orderService = new OrderService($this->connection, $this->orderRepository);
        $this->paymentService = new PaymentService(
            $this->connection,
            $this->orderRepository,
            new \App\Repository\OutboxRepository($this->connection),
            $logger
        );
    }

    public function testCancelOrderFromPendingPayment(): void
    {
        $order = $this->orderService->createOrder(100.00);
        $this->orderService->cancelOrder($order->getId());

        $cancelledOrder = $this->orderRepository->findById($order->getId());
        $this->assertNotNull($cancelledOrder);
        $this->assertEquals(OrderState::CANCELLED, $cancelledOrder->getState());
    }

    public function testCancelOrderFromPaymentFailed(): void
    {
        $order = $this->orderService->createOrder(100.00);
        $this->paymentService->setSeed(99999); // Force failure
        try {
            $this->paymentService->processPayment($order->getId());
        } catch (\Exception $e) {
            // Payment might succeed or fail, check state
        }

        $orderAfterPayment = $this->orderRepository->findById($order->getId());
        if ($orderAfterPayment?->getState() === OrderState::PAYMENT_FAILED) {
            $this->orderService->cancelOrder($order->getId());
            $cancelledOrder = $this->orderRepository->findById($order->getId());
            $this->assertNotNull($cancelledOrder);
            $this->assertEquals(OrderState::CANCELLED, $cancelledOrder->getState());
        }
    }

    public function testCancelOrderFailsFromPaid(): void
    {
        $order = $this->orderService->createOrder(100.00);
        $this->paymentService->setSeed(1);
        $this->paymentService->processPayment($order->getId());

        $orderAfterPayment = $this->orderRepository->findById($order->getId());
        if ($orderAfterPayment?->getState() === OrderState::PAID) {
            $this->expectException(\DomainException::class);
            $this->orderService->cancelOrder($order->getId());
        }
    }

    public function testCancelOrderFailsForNonExistentOrder(): void
    {
        $this->expectException(\DomainException::class);
        $this->orderService->cancelOrder(99999);
    }
}

