.PHONY: help up down restart logs build test artisan composer npm prod-build prod-up prod-down

help:
	@echo "Comandos disponibles para PlaceToPay:"
	@echo "  make up          - Levantar entorno de desarrollo (docker-compose)"
	@echo "  make down        - Detener entorno de desarrollo"
	@echo "  make restart     - Reiniciar contenedores de desarrollo"
	@echo "  make build       - Reconstruir contenedor de desarrollo"
	@echo "  make logs        - Ver logs de contenedores de desarrollo"
	@echo "  make test        - Ejecutar suite de pruebas (PHPUnit)"
	@echo "  make prod-build  - Construir imagen standalone de producción"
	@echo "  make prod-up     - Levantar stack completo de producción"
	@echo "  make prod-down   - Detener stack de producción"

up:
	docker compose up -d

down:
	docker compose down

restart:
	docker compose restart

build:
	docker compose build

logs:
	docker compose logs -f

test:
	docker compose exec app php artisan test

prod-build:
	docker build -f Dockerfile.prod -t placetopay-app:latest .

prod-up:
	docker compose -f docker-compose.prod.yml up -d

prod-down:
	docker compose -f docker-compose.prod.yml down
