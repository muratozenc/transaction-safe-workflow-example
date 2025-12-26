<?php

declare(strict_types=1);

namespace App\Domain;

final class OutboxEvent
{
    public function __construct(
        private int $id,
        private int $aggregateId,
        private string $type,
        private array $payload,
        private OutboxEventStatus $status,
        private \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $processedAt = null
    ) {
    }

    public static function create(int $aggregateId, string $type, array $payload): self
    {
        return new self(
            0,
            $aggregateId,
            $type,
            $payload,
            OutboxEventStatus::PENDING,
            new \DateTimeImmutable()
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getAggregateId(): int
    {
        return $this->aggregateId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getStatus(): OutboxEventStatus
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function markAsProcessed(): void
    {
        if ($this->status !== OutboxEventStatus::PENDING) {
            throw new \DomainException("Cannot mark event as processed: current status is {$this->status->value}");
        }
        $this->status = OutboxEventStatus::PROCESSED;
        $this->processedAt = new \DateTimeImmutable();
    }
}

