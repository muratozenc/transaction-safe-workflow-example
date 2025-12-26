<?php

declare(strict_types=1);

namespace App\Application;

use App\Database\MigrationRunner;
use App\Repository\OrderNotificationRepository;
use App\Repository\OrderRepository;
use App\Repository\OutboxRepository;
use App\Service\OrderService;
use App\Service\OutboxWorkerService;
use App\Service\PaymentService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Predis\Client as RedisClient;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

final class ContainerFactory
{
    public static function create(): ContainerInterface
    {
        $container = new \DI\Container();

        $envPath = __DIR__ . '/../../';
        if (file_exists($envPath . '.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($envPath);
            $dotenv->load();
        }

        $container->set(LoggerInterface::class, function () {
            $logger = new Logger('app');
            $logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
            return $logger;
        });

        $container->set(Connection::class, function () {
            return DriverManager::getConnection([
                'dbname' => $_ENV['DB_NAME'],
                'user' => $_ENV['DB_USER'],
                'password' => $_ENV['DB_PASSWORD'],
                'host' => $_ENV['DB_HOST'],
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'driver' => 'pdo_mysql',
            ]);
        });

        $container->set(RedisClient::class, function () {
            return new RedisClient([
                'host' => $_ENV['REDIS_HOST'] ?? 'redis',
                'port' => (int) ($_ENV['REDIS_PORT'] ?? 6379),
            ]);
        });

        $container->set(OrderRepository::class, function (ContainerInterface $c) {
            return new OrderRepository($c->get(Connection::class));
        });

        $container->set(OutboxRepository::class, function (ContainerInterface $c) {
            return new OutboxRepository($c->get(Connection::class));
        });

        $container->set(OrderNotificationRepository::class, function (ContainerInterface $c) {
            return new OrderNotificationRepository($c->get(Connection::class));
        });

        $container->set(OrderService::class, function (ContainerInterface $c) {
            return new OrderService($c->get(Connection::class), $c->get(OrderRepository::class));
        });

        $container->set(PaymentService::class, function (ContainerInterface $c) {
            return new PaymentService(
                $c->get(Connection::class),
                $c->get(OrderRepository::class),
                $c->get(OutboxRepository::class),
                $c->get(LoggerInterface::class)
            );
        });

        $container->set(OutboxWorkerService::class, function (ContainerInterface $c) {
            return new OutboxWorkerService(
                $c->get(Connection::class),
                $c->get(OutboxRepository::class),
                $c->get(OrderNotificationRepository::class),
                $c->get(RedisClient::class),
                $c->get(LoggerInterface::class)
            );
        });

        $container->set(MigrationRunner::class, function (ContainerInterface $c) {
            return new MigrationRunner(
                $c->get(Connection::class),
                $c->get(LoggerInterface::class),
                __DIR__ . '/../../migrations'
            );
        });

        $container->set(\App\Controller\OrderController::class, function (ContainerInterface $c) {
            return new \App\Controller\OrderController(
                $c->get(OrderService::class),
                $c->get(PaymentService::class),
                $c->get(OrderRepository::class)
            );
        });

        $container->set(\App\Controller\OutboxController::class, function (ContainerInterface $c) {
            return new \App\Controller\OutboxController(
                $c->get(OutboxWorkerService::class)
            );
        });

        return $container;
    }

    public static function createApp(): App
    {
        $container = self::create();
        AppFactory::setContainer($container);
        return AppFactory::create();
    }
}

