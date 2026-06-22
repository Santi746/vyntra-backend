FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    libpq-dev \
    libssl-dev \
    libbrotli-dev \
    unzip \
    git \
    && docker-php-ext-install pcntl pdo_pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN pecl install redis swoole \
    && docker-php-ext-enable redis swoole

COPY . /app

WORKDIR /app

EXPOSE 8000

ENTRYPOINT ["php", "artisan", "octane:start", "--server=swoole"]
