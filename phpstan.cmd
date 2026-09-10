@echo off
set CMD=%*
if "%CMD%"=="" set CMD=analyse
docker compose exec app php vendor/bin/phpstan %CMD%
