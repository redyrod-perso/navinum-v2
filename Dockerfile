FROM dunglas/frankenphp:latest

WORKDIR /app

# Dépendances système
RUN apt-get update && apt-get install -y unzip && rm -rf /var/lib/apt/lists/*

# Extensions PHP
RUN install-php-extensions pdo_pgsql intl

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installer Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copier l'application
COPY . /app

ARG APP_ENV=prod
ENV APP_ENV=$APP_ENV

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && php /usr/local/bin/composer --version

ENV COMPOSER_ALLOW_SUPERUSER=1

# Installer les dépendances selon l'environnement
RUN if [ "$APP_ENV" = "prod" ]; then \
      php /usr/local/bin/composer install --no-dev --optimize-autoloader; \
    else \
      php /usr/local/bin/composer install --optimize-autoloader; \
    fi

RUN composer dump-autoload --optimize
#RUN php bin/console importmap:install
#RUN php bin/console asset-map:compile


# Installer les dépendances (APP_ENV=prod évite le chargement des bundles dev)
# Script d'entrypoint pour init DB + migrations
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
