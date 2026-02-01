FROM dunglas/frankenphp:1.11.1-builder-php8.4.17

# Installer git
RUN apt-get update && apt-get install -y \
    git \
    zip \
    libzip-dev \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

ARG APP_ENV

WORKDIR /app

COPY . /app

# Extensions PHP
RUN install-php-extensions pdo_pgsql intl
# extension PHP zip
RUN docker-php-ext-install zip

#RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
