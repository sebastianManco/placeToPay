FROM php:8.2-cli-alpine

RUN apk add --no-cache libzip-dev \
    && docker-php-ext-install pdo_mysql bcmath zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "server.php"]
