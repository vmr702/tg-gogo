FROM php:8.3-fpm-alpine

RUN apk add --no-cache postgresql-dev bash \
    && docker-php-ext-install pdo pdo_pgsql

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
