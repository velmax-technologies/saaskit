FROM php:8.5-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip

RUN docker-php-ext-install \
    bcmath \
    intl \
    pdo_mysql \
    zip

RUN apk add --no-cache --virtual .redis-build-deps $PHPIZE_DEPS linux-headers \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .redis-build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
