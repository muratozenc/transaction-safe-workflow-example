<?php

declare(strict_types=1);

namespace App\Application;

use App\Controller\OrderController;
use App\Controller\OutboxController;
use Slim\App;

final class Routes
{
    public static function register(App $app): void
    {
        $orderController = $app->getContainer()->get(OrderController::class);
        $outboxController = $app->getContainer()->get(OutboxController::class);

        $app->post('/orders', [$orderController, 'create']);
        $app->post('/orders/{id}/pay', [$orderController, 'pay']);
        $app->post('/orders/{id}/cancel', [$orderController, 'cancel']);
        $app->post('/outbox/worker/run-once', [$outboxController, 'runOnce']);
    }
}

