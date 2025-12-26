<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\OrderRepository;
use App\Service\OrderService;
use App\Service\PaymentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpNotFoundException;

final class OrderController
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PaymentService $paymentService,
        private readonly OrderRepository $orderRepository
    ) {
    }

    public function create(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!isset($body['total_amount']) || !is_numeric($body['total_amount'])) {
            throw new HttpBadRequestException($request, 'total_amount is required and must be numeric');
        }

        $totalAmount = (float) $body['total_amount'];
        if ($totalAmount <= 0) {
            throw new HttpBadRequestException($request, 'total_amount must be greater than 0');
        }

        $order = $this->orderService->createOrder($totalAmount);

        $response->getBody()->write(json_encode([
            'id' => $order->getId(),
            'state' => $order->getState()->value,
            'total_amount' => $order->getTotalAmount(),
            'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
        ], JSON_THROW_ON_ERROR));

        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public function pay(Request $request, Response $response, array $args): Response
    {
        $orderId = (int) ($args['id'] ?? 0);
        if ($orderId <= 0) {
            throw new HttpBadRequestException($request, 'Invalid order ID');
        }

        try {
            $this->paymentService->processPayment($orderId);
        } catch (\DomainException $e) {
            throw new HttpBadRequestException($request, $e->getMessage());
        }

        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            throw new HttpNotFoundException($request, "Order {$orderId} not found");
        }

        $response->getBody()->write(json_encode([
            'id' => $order->getId(),
            'state' => $order->getState()->value,
            'total_amount' => $order->getTotalAmount(),
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function cancel(Request $request, Response $response, array $args): Response
    {
        $orderId = (int) ($args['id'] ?? 0);
        if ($orderId <= 0) {
            throw new HttpBadRequestException($request, 'Invalid order ID');
        }

        try {
            $this->orderService->cancelOrder($orderId);
        } catch (\DomainException $e) {
            throw new HttpBadRequestException($request, $e->getMessage());
        }

        $order = $this->orderRepository->findById($orderId);
        if ($order === null) {
            throw new HttpNotFoundException($request, "Order {$orderId} not found");
        }

        $response->getBody()->write(json_encode([
            'id' => $order->getId(),
            'state' => $order->getState()->value,
            'total_amount' => $order->getTotalAmount(),
        ], JSON_THROW_ON_ERROR));

        return $response->withHeader('Content-Type', 'application/json');
    }
}

