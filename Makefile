.PHONY: help install test migrate up down logs clean

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "  %-15s %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies
	docker-compose exec php composer install

up: ## Start Docker containers
	docker-compose up -d

down: ## Stop Docker containers
	docker-compose down

logs: ## Show Docker logs
	docker-compose logs -f

migrate: ## Run database migrations
	docker-compose exec php php scripts/migrate.php

test: ## Run all tests
	docker-compose exec php composer test

test-unit: ## Run unit tests only
	docker-compose exec php composer test -- --testsuite=Unit

test-integration: ## Run integration tests only
	docker-compose exec php composer test -- --testsuite=Integration

phpstan: ## Run PHPStan
	docker-compose exec php composer phpstan

cs-fix: ## Fix code style
	docker-compose exec php composer cs-fix

cs-check: ## Check code style
	docker-compose exec php composer cs-check

clean: ## Clean up Docker volumes and cache
	docker-compose down -v
	docker-compose exec php rm -rf vendor .phpunit.cache .php-cs-fixer.cache || true

