<?php

declare(strict_types=1);

namespace App\Domain;

enum OrderState: string
{
    case PENDING_PAYMENT = 'PENDING_PAYMENT';
    case PAID = 'PAID';
    case PAYMENT_FAILED = 'PAYMENT_FAILED';
    case CANCELLED = 'CANCELLED';

    public function canTransitionTo(OrderState $target): bool
    {
        return match ($this) {
            self::PENDING_PAYMENT => in_array($target, [self::PAID, self::PAYMENT_FAILED, self::CANCELLED], true),
            self::PAYMENT_FAILED => $target === self::CANCELLED,
            self::PAID, self::CANCELLED => false,
        };
    }

    public function canCancel(): bool
    {
        return match ($this) {
            self::PENDING_PAYMENT, self::PAYMENT_FAILED => true,
            self::PAID, self::CANCELLED => false,
        };
    }
}

