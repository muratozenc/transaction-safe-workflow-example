<?php

declare(strict_types=1);

namespace App\Domain;

enum OutboxEventStatus: string
{
    case PENDING = 'PENDING';
    case PROCESSED = 'PROCESSED';
}

