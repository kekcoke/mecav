FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache postgresql-dev linux-headers $PHPIZE_DEPS \
    && pecl install grpc protobuf \
    && docker-php-ext-enable grpc protobuf \
    && docker-php-ext-install pdo pdo_pgsql pcntl \
    && apk del $PHPIZE_DEPS

WORKDIR /var/www/html
COPY services/laravel/composer.json services/laravel/composer.lock ./
RUN php -r "copy('https://getcomposer.org/installer', 'cs.php');" \
    && php cs.php --install-dir=/usr/local/bin --filename=composer \
    && rm cs.php \
    && composer install --no-dev --no-interaction --optimize-autoloader

COPY services/laravel .
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
