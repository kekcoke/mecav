FROM php:8.3-fpm-alpine AS builder

# Install build dependencies and compile gRPC
RUN apk add --no-cache postgresql-dev linux-headers $PHPIZE_DEPS \
    && export MAKEFLAGS="-j2" \
    && php -d memory_limit=-1 $(which pecl) install grpc protobuf \
    && docker-php-ext-install pdo pdo_pgsql pcntl \
    && rm -rf /tmp/pear

# Final Stage
FROM php:8.3-fpm-alpine

# Install runtime and build deps, then install gRPC
RUN apk add --no-cache postgresql-libs nginx supervisor linux-headers zlib-dev $PHPIZE_DEPS \
    && pecl install grpc protobuf \
    && docker-php-ext-install pdo pdo_pgsql pcntl \
    && rm -rf /tmp/pear

WORKDIR /var/www/html

# Copy application code first so artisan is available for composer hooks
COPY services/laravel .

# Composer installation
RUN php -r "copy('https://getcomposer.org/installer', 'cs.php');" \
    && php cs.php --install-dir=/usr/local/bin --filename=composer \
    && rm cs.php \
    && composer update --no-dev --no-interaction --prefer-dist --ignore-platform-reqs

RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && docker-php-ext-enable grpc \
    && echo "extension=grpc.so" > /usr/local/etc/php/conf.d/docker-php-ext-grpc.ini \
    && chown -R www-data:www-data storage bootstrap/cache

# Copy nginx and supervisor configs
COPY services/laravel/nginx.conf /etc/nginx/http.d/default.conf
COPY services/laravel/supervisord.conf /etc/supervisord.conf

EXPOSE 8000
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
