<?php

declare(strict_types=1);

use App\Application\ContainerFactory;
use App\Application\Routes;
use Slim\Middleware\ErrorMiddleware;

require_once __DIR__ . '/../vendor/autoload.php';

$app = ContainerFactory::createApp();

$app->addErrorMiddleware(true, true, true);

Routes::register($app);

$app->run();

