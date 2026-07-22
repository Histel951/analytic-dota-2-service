FROM php:8.3-cli

# Устанавливаем зависимости для сборки
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    supervisor \
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

# Создаем дефолтный конфиг supervisor
RUN mkdir -p /etc/supervisor/conf.d/ && \
    echo '[supervisord]\nnodaemon=true\nlogfile=/dev/null\n\n[program:placeholder]\ncommand=echo "Waiting for config"\nautostart=true\n' > /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD supervisorctl status || exit 1

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]