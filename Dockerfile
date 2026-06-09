FROM composer:2.2 AS composer

FROM php:7.2-apache

RUN sed -i \
        -e 's|deb.debian.org/debian|archive.debian.org/debian|g' \
        -e 's|security.debian.org/debian-security|archive.debian.org/debian-security|g' \
        -e '/stretch-updates/d' \
        /etc/apt/sources.list \
    && printf 'Acquire::Check-Valid-Until "false";\n' > /etc/apt/apt.conf.d/99archive \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libicu-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install \
        intl \
        pdo_mysql \
        zip \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

COPY . .
COPY docker/parameters.yml app/config/parameters.yml

ENV DATABASE_HOST=127.0.0.1 \
    DATABASE_PORT=3306 \
    DATABASE_NAME=chessmate \
    DATABASE_USER=chessmate \
    DATABASE_PASSWORD=chessmate \
    MAILER_TRANSPORT=smtp \
    MAILER_HOST=localhost \
    MAILER_USER=no-reply@chessmate.local \
    MAILER_PASSWORD="" \
    APP_SECRET=build-time-secret

RUN composer install \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && chown -R www-data:www-data var web/bundles

COPY docker/entrypoint.sh /usr/local/bin/chessmate-entrypoint
RUN chmod +x /usr/local/bin/chessmate-entrypoint

ENTRYPOINT ["chessmate-entrypoint"]
CMD ["apache2-foreground"]
