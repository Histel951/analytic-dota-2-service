FROM php:8.3-fpm

# Устанавливаем зависимости для сборки
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    g++ \
    make \
    && rm -rf /var/lib/apt/lists/*

# Сборка librdkafka из исходников (актуальная версия для Kafka 4.x)
RUN git clone --branch v2.10.0 --depth 1 https://github.com/confluentinc/librdkafka.git /tmp/librdkafka \
    && cd /tmp/librdkafka \
    && ./configure --prefix=/usr \
    && make \
    && make install \
    && rm -rf /tmp/librdkafka

# Устанавливаем PHP расширения
RUN docker-php-ext-install pdo pdo_pgsql pcntl

# Устанавливаем rdkafka через pecl (будет использовать свежую librdkafka)
RUN pecl install rdkafka \
    && docker-php-ext-enable rdkafka

# Устанавливаем composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www