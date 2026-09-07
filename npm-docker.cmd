@echo off
docker run --rm -v "%cd%:/app" -w /app node:20-alpine npm %*
