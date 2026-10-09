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
COPY entrypoint.sh /usr/local/bin/saaskit-entrypoint

RUN chmod +x /usr/local/bin/saaskit-entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/saaskit-entrypoint"]
CMD ["php-fpm"]
