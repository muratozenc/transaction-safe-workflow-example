<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\OutboxEvent;
use App\Repository\OrderNotificationRepository;
use App\Repository\OutboxRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Predis\Client as RedisClient;
use Psr\Log\LoggerInterface;

final class OutboxWorkerService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly OutboxRepository $outboxRepository,
        private readonly OrderNotificationRepository $notificationRepository,
        private readonly RedisClient $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    public function processNextEvent(): ?OutboxEvent
    {
        $event = $this->outboxRepository->findNextPending();
        if ($event === null) {
            return null;
        }

        if ($this->notificationRepository->existsForOutboxEvent($event->getId())) {
            $this->connection->beginTransaction();
            try {
                $event->markAsProcessed();
                $this->outboxRepository->update($event);
                $this->connection->commit();
                $this->logger->info("Outbox event {$event->getId()} already processed, skipping");
                return $event;
            } catch (\Exception $e) {
                $this->connection->rollBack();
                throw $e;
            }
        }

        $this->connection->beginTransaction();
        try {
            $event->markAsProcessed();
            $this->outboxRepository->update($event);

            $this->redis->lpush('order_notifications', json_encode([
                'outbox_event_id' => $event->getId(),
                'order_id' => $event->getAggregateId(),
                'type' => $event->getType(),
                'payload' => $event->getPayload(),
            ], JSON_THROW_ON_ERROR));

            try {
                $this->notificationRepository->create(
                    $event->getId(),
                    $event->getAggregateId(),
                    $event->getType(),
                    $this->buildNotificationMessage($event)
                );
            } catch (Exception $e) {
                if (str_contains($e->getMessage(), 'Duplicate entry') || 
                    str_contains($e->getMessage(), 'unique_outbox_event')) {
                    $this->logger->info("Notification already exists for outbox event {$event->getId()}");
                } else {
                    throw $e;
                }
            }

            $this->connection->commit();
            $this->logger->info("Processed outbox event {$event->getId()}");
            return $event;
        } catch (\Exception $e) {
            $this->connection->rollBack();
            $this->logger->error("Failed to process outbox event {$event->getId()}: {$e->getMessage()}");
            throw $e;
        }
    }

    private function buildNotificationMessage(OutboxEvent $event): string
    {
        return match ($event->getType()) {
            'ORDER_PAID' => "Order {$event->getAggregateId()} has been paid successfully",
            'PAYMENT_FAILED' => "Payment failed for order {$event->getAggregateId()}",
            default => "Event {$event->getType()} for order {$event->getAggregateId()}",
        };
    }
}

