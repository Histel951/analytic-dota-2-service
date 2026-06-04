FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    librdkafka-dev \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

RUN pecl install rdkafka \
    && docker-php-ext-enable rdkafka

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www