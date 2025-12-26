<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Order;
use App\Domain\OrderState;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testCreateOrderHasPendingPaymentState(): void
    {
        $order = Order::create(1, 100.50);
        $this->assertEquals(OrderState::PENDING_PAYMENT, $order->getState());
        $this->assertEquals(100.50, $order->getTotalAmount());
    }

    public function testMarkAsPaidTransitionsFromPendingPayment(): void
    {
        $order = Order::create(1, 100.50);
        $order->markAsPaid();
        $this->assertEquals(OrderState::PAID, $order->getState());
    }

    public function testMarkAsPaidFailsFromInvalidState(): void
    {
        $order = Order::create(1, 100.50);
        $order->markAsPaymentFailed();
        $this->expectException(\DomainException::class);
        $order->markAsPaid();
    }

    public function testMarkAsPaymentFailedTransitionsFromPendingPayment(): void
    {
        $order = Order::create(1, 100.50);
        $order->markAsPaymentFailed();
        $this->assertEquals(OrderState::PAYMENT_FAILED, $order->getState());
    }

    public function testCancelFromPendingPayment(): void
    {
        $order = Order::create(1, 100.50);
        $order->cancel();
        $this->assertEquals(OrderState::CANCELLED, $order->getState());
    }

    public function testCancelFromPaymentFailed(): void
    {
        $order = Order::create(1, 100.50);
        $order->markAsPaymentFailed();
        $order->cancel();
        $this->assertEquals(OrderState::CANCELLED, $order->getState());
    }

    public function testCancelFailsFromPaid(): void
    {
        $order = Order::create(1, 100.50);
        $order->markAsPaid();
        $this->expectException(\DomainException::class);
        $order->cancel();
    }
}

