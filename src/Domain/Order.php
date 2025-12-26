<?php

declare(strict_types=1);

namespace App\Domain;

final class Order
{
    public function __construct(
        private int $id,
        private OrderState $state,
        private float $totalAmount,
        private \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt
    ) {
    }

    public static function create(int $id, float $totalAmount): self
    {
        $now = new \DateTimeImmutable();
        return new self(
            $id,
            OrderState::PENDING_PAYMENT,
            $totalAmount,
            $now,
            $now
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getState(): OrderState
    {
        return $this->state;
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markAsPaid(): void
    {
        if (!$this->state->canTransitionTo(OrderState::PAID)) {
            throw new \DomainException("Cannot transition from {$this->state->value} to PAID");
        }
        $this->state = OrderState::PAID;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsPaymentFailed(): void
    {
        if (!$this->state->canTransitionTo(OrderState::PAYMENT_FAILED)) {
            throw new \DomainException("Cannot transition from {$this->state->value} to PAYMENT_FAILED");
        }
        $this->state = OrderState::PAYMENT_FAILED;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        if (!$this->state->canCancel()) {
            throw new \DomainException("Cannot cancel order in state {$this->state->value}");
        }
        $this->state = OrderState::CANCELLED;
        $this->updatedAt = new \DateTimeImmutable();
    }
}

