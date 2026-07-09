# Stage 1 : deps + build
FROM php:8.4-fpm-alpine AS base

# Extensions système nécessaires
RUN apk add --no-cache \
        acl \
        fcgi \
        file \
        gettext \
        git \
        icu-dev \
        libpq-dev \
        libsodium-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install \
        fileinfo \
        intl \
        opcache \
        pdo \
        pdo_pgsql \
        sodium

# Limites d'upload PHP
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# -------------------------------------------------------------------
# Stage 2 : dev (montage du code en volume, pas de build assets)
# -------------------------------------------------------------------
FROM base AS dev

ENV APP_ENV=dev
ENV APP_DEBUG=1

# Installe les dépendances (avec dev)
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-interaction --prefer-dist

COPY . .

RUN composer dump-autoload --optimize

EXPOSE 9000

# -------------------------------------------------------------------
# Stage 3 : prod
# -------------------------------------------------------------------
FROM base AS prod

ENV APP_ENV=prod
ENV APP_DEBUG=0

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize \
    && php bin/console cache:warmup \
    && mkdir -p public/uploads/photos public/uploads/documents \
    && chown -R www-data:www-data public/uploads var

EXPOSE 9000
