<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\OrderState;
use PHPUnit\Framework\TestCase;

final class OrderStateTest extends TestCase
{
    public function testPendingPaymentCanTransitionToPaid(): void
    {
        $state = OrderState::PENDING_PAYMENT;
        $this->assertTrue($state->canTransitionTo(OrderState::PAID));
    }

    public function testPendingPaymentCanTransitionToPaymentFailed(): void
    {
        $state = OrderState::PENDING_PAYMENT;
        $this->assertTrue($state->canTransitionTo(OrderState::PAYMENT_FAILED));
    }

    public function testPendingPaymentCanTransitionToCancelled(): void
    {
        $state = OrderState::PENDING_PAYMENT;
        $this->assertTrue($state->canTransitionTo(OrderState::CANCELLED));
    }

    public function testPaymentFailedCanTransitionToCancelled(): void
    {
        $state = OrderState::PAYMENT_FAILED;
        $this->assertTrue($state->canTransitionTo(OrderState::CANCELLED));
    }

    public function testPaymentFailedCannotTransitionToPaid(): void
    {
        $state = OrderState::PAYMENT_FAILED;
        $this->assertFalse($state->canTransitionTo(OrderState::PAID));
    }

    public function testPaidCannotTransitionToAnyState(): void
    {
        $state = OrderState::PAID;
        $this->assertFalse($state->canTransitionTo(OrderState::PENDING_PAYMENT));
        $this->assertFalse($state->canTransitionTo(OrderState::PAYMENT_FAILED));
        $this->assertFalse($state->canTransitionTo(OrderState::CANCELLED));
    }

    public function testCancelledCannotTransitionToAnyState(): void
    {
        $state = OrderState::CANCELLED;
        $this->assertFalse($state->canTransitionTo(OrderState::PENDING_PAYMENT));
        $this->assertFalse($state->canTransitionTo(OrderState::PAID));
        $this->assertFalse($state->canTransitionTo(OrderState::PAYMENT_FAILED));
    }

    public function testPendingPaymentCanCancel(): void
    {
        $state = OrderState::PENDING_PAYMENT;
        $this->assertTrue($state->canCancel());
    }

    public function testPaymentFailedCanCancel(): void
    {
        $state = OrderState::PAYMENT_FAILED;
        $this->assertTrue($state->canCancel());
    }

    public function testPaidCannotCancel(): void
    {
        $state = OrderState::PAID;
        $this->assertFalse($state->canCancel());
    }

    public function testCancelledCannotCancel(): void
    {
        $state = OrderState::CANCELLED;
        $this->assertFalse($state->canCancel());
    }
}

