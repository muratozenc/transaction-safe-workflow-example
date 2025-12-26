# Transaction-Safe Workflow Example

A production-minded demonstration of transaction-safe workflows in PHP using the **Outbox Pattern** and **Idempotent Consumer** pattern. This example shows how to maintain consistency between transactional writes and asynchronous side effects without distributed transactions.

## Overview

This project demonstrates a payment processing workflow for orders with the following key patterns:

1. **Transactional Writes + Outbox Pattern**: Ensures order state changes and event publishing happen atomically
2. **Idempotent Consumer**: Ensures outbox events can be safely retried without duplicating side effects
3. **State Machine**: Enforces valid state transitions for orders

## Why These Patterns?

### The Problem: Distributed Transactions Don't Scale

In a microservices architecture, you often need to:
1. Update a database record (e.g., mark order as PAID)
2. Publish an event to a message queue (e.g., notify other services)

The naive approach would be:
```php
$db->beginTransaction();
$order->markAsPaid();
$db->commit();
$queue->publish('ORDER_PAID', $order); // What if this fails?
```

**Problems:**
- If the queue publish fails, the order is marked as paid but no one knows
- If the database commit fails, the event might have been published
- Two-phase commit (2PC) is slow, complex, and doesn't work across different systems

### The Solution: Transactional Outbox Pattern

Instead of publishing directly to the queue, we write events to an `outbox_events` table **in the same database transaction**:

```php
$db->beginTransaction();
$order->markAsPaid();
$db->update('orders', ...);
$db->insert('outbox_events', ['type' => 'ORDER_PAID', ...]);
$db->commit(); // Both writes are atomic
```

Then, a separate worker process:
1. Reads pending events from `outbox_events`
2. Publishes to the queue
3. Marks the event as processed

**Benefits:**
- ✅ Atomicity: Order update and event creation happen in one transaction
- ✅ Reliability: Events are never lost (they're in the database)
- ✅ No distributed transactions needed
- ✅ Works across any database/queue combination

### Why Idempotent Consumers Matter

When processing outbox events, failures can happen:
- Network issues when publishing to Redis
- Database errors when creating notifications
- Worker crashes mid-processing

**Without idempotency:**
- Retrying a failed event could create duplicate notifications
- Duplicate emails, duplicate webhooks, duplicate charges

**With idempotency:**
- Check if notification already exists for this outbox event
- If it exists, skip processing (idempotent)
- Unique constraint on `outbox_event_id` prevents duplicates

## Architecture

```
┌─────────────┐
│   API       │
│  (Slim 4)   │
└──────┬──────┘
       │
       ▼
┌─────────────┐
│   Payment      │
│   Service   │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│  Database Transaction        │
│  ┌──────────┐  ┌──────────┐ │
│  │ orders   │  │ outbox_   │ │
│  │ (PAID)   │  │ events    │ │
│  └──────────┘  └──────────┘ │
└─────────────────────────────┘
       │
       ▼
┌─────────────┐
│  Outbox     │
│  Worker     │
└──────┬──────┘
       │
       ▼
┌─────────────────────────────┐
│  1. Mark event processed    │
│  2. Push to Redis queue     │
│  3. Create notification     │
│     (idempotent check)      │
└─────────────────────────────┘
```

## Domain Model

### Order States

- `PENDING_PAYMENT`: Initial state, waiting for payment
- `PAID`: Payment succeeded
- `PAYMENT_FAILED`: Payment failed
- `CANCELLED`: Order was cancelled (only from PENDING_PAYMENT or PAYMENT_FAILED)

### State Transitions

```
PENDING_PAYMENT ──[pay succeeds]──> PAID
                ──[pay fails]──> PAYMENT_FAILED
                ──[cancel]──> CANCELLED

PAYMENT_FAILED ──[cancel]──> CANCELLED

PAID ──[no transitions allowed]──> ❌
CANCELLED ──[no transitions allowed]──> ❌
```

## API Endpoints

### `POST /orders`

Creates a new order in `PENDING_PAYMENT` state.

**Request:**
```json
{
  "total_amount": 100.50
}
```

**Response:**
```json
{
  "id": 1,
  "state": "PENDING_PAYMENT",
  "total_amount": 100.50,
  "created_at": "2024-01-15 10:30:00"
}
```

### `POST /orders/{id}/pay`

Processes payment for an order. This is **transaction-safe**:
- Updates order state (PAID or PAYMENT_FAILED)
- Creates outbox event (ORDER_PAID or PAYMENT_FAILED)
- Both happen in a single database transaction

**Response:**
```json
{
  "id": 1,
  "state": "PAID",
  "total_amount": 100.50
}
```

### `POST /orders/{id}/cancel`

Cancels an order. Only allowed if state is `PENDING_PAYMENT` or `PAYMENT_FAILED`.

**Response:**
```json
{
  "id": 1,
  "state": "CANCELLED",
  "total_amount": 100.50
}
```

### `POST /outbox/worker/run-once`

Processes one pending outbox event. This is **idempotent**:
- Checks if notification already exists
- If exists, marks event as processed and skips
- If not, processes event and creates notification

**Response:**
```json
{
  "processed": true,
  "event": {
    "id": 1,
    "aggregate_id": 1,
    "type": "ORDER_PAID",
    "status": "PROCESSED"
  }
}
```

## Database Schema

### `orders`
- `id`: Primary key
- `state`: Order state (PENDING_PAYMENT, PAID, PAYMENT_FAILED, CANCELLED)
- `total_amount`: Order total
- `created_at`, `updated_at`: Timestamps

### `outbox_events`
- `id`: Primary key
- `aggregate_id`: ID of the order
- `type`: Event type (ORDER_PAID, PAYMENT_FAILED)
- `payload`: JSON payload
- `status`: PENDING or PROCESSED
- `created_at`, `processed_at`: Timestamps

### `order_notifications`
- `id`: Primary key
- `outbox_event_id`: Foreign key to outbox_events (UNIQUE constraint)
- `order_id`: Order ID
- `notification_type`: Type of notification
- `message`: Notification message
- `created_at`: Timestamp

**Key constraint:** `UNIQUE KEY unique_outbox_event (outbox_event_id)` ensures idempotency.

## Setup

### Prerequisites

- Docker and Docker Compose
- PHP 8.3+ (or use Docker)

### Installation

1. Clone the repository:
```bash
git clone <repository-url>
cd transaction-safe-workflow-example
```

2. Copy environment file:
```bash
cp .env.example .env
```

3. Start services:
```bash
docker-compose up -d
```

4. Install dependencies:
```bash
docker-compose exec php composer install
```

5. Run migrations:
```bash
docker-compose exec php php -r "
require 'vendor/autoload.php';
\$container = \App\Application\ContainerFactory::create();
\$runner = \$container->get(\App\Database\MigrationRunner::class);
\$runner->run();
"
```

Or create a simple migration script:
```bash
docker-compose exec php php scripts/migrate.php
```

## Running Tests

### Unit Tests
```bash
docker-compose exec php composer test -- --testsuite=Unit
```

### Integration Tests
```bash
docker-compose exec php composer test -- --testsuite=Integration
```

### All Tests
```bash
docker-compose exec php composer test
```

## Code Quality

### PHPStan (Static Analysis)
```bash
docker-compose exec php composer phpstan
```

### PHP-CS-Fixer (Code Style)
```bash
# Check
docker-compose exec php composer cs-check

# Fix
docker-compose exec php composer cs-fix
```

## Running the API

The API runs on PHP-FPM. You can use a web server like nginx or Apache, or use PHP's built-in server for development:

```bash
docker-compose exec php php -S 0.0.0.0:8000 -t public
```

Then access: `http://localhost:8000`

## Example Workflow

1. **Create an order:**
```bash
curl -X POST http://localhost:8000/orders \
  -H "Content-Type: application/json" \
  -d '{"total_amount": 100.50}'
```

2. **Process payment:**
```bash
curl -X POST http://localhost:8000/orders/1/pay
```

3. **Process outbox event:**
```bash
curl -X POST http://localhost:8000/outbox/worker/run-once
```

4. **Verify idempotency (run again):**
```bash
curl -X POST http://localhost:8000/outbox/worker/run-once
# Should return the same event, but no duplicate notification created
```

## Key Implementation Details

### Transaction Safety in PaymentService

```php
public function processPayment(int $orderId): void
{
    $this->connection->beginTransaction();
    try {
        // Update order state
        $order->markAsPaid();
        $this->orderRepository->update($order);
        
        // Create outbox event in SAME transaction
        $outboxEvent = OutboxEvent::create(...);
        $this->outboxRepository->create($outboxEvent);
        
        $this->connection->commit(); // Atomic!
    } catch (\Exception $e) {
        $this->connection->rollBack();
        throw $e;
    }
}
```

### Idempotency in OutboxWorkerService

```php
public function processNextEvent(): ?OutboxEvent
{
    $event = $this->outboxRepository->findNextPending();
    
    // Idempotency check
    if ($this->notificationRepository->existsForOutboxEvent($event->getId())) {
        // Already processed, skip
        $event->markAsProcessed();
        $this->outboxRepository->update($event);
        return $event;
    }
    
    // Process event...
    // Unique constraint prevents duplicate notifications
}
```

## Production Considerations

1. **Outbox Worker**: In production, run as a daemon/background job that continuously polls for pending events
2. **Error Handling**: Implement retry logic with exponential backoff for failed event processing
3. **Monitoring**: Add metrics for outbox event processing latency and failure rates
4. **Dead Letter Queue**: Handle events that fail repeatedly after retries
5. **Event Ordering**: Consider partitioning by aggregate_id if strict ordering is required

## Why No Distributed Transactions?

Distributed transactions (2PC) have significant drawbacks:
- **Performance**: High latency due to coordination overhead
- **Availability**: All participants must be available
- **Complexity**: Difficult to implement and debug
- **Lock Contention**: Long-running transactions hold locks

The Outbox Pattern provides:
- ✅ Better performance (single database transaction)
- ✅ Better availability (queue can be down, events wait in DB)
- ✅ Simpler implementation
- ✅ Works across any database/queue combination

## License

GPL 3.0

