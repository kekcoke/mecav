FROM php:8.4-fpm-alpine AS builder

# Install build dependencies
RUN apk add --no-cache postgresql-dev linux-headers $PHPIZE_DEPS \
    && export MAKEFLAGS="-j2" \
    && php -d memory_limit=-1 $(which pecl) install grpc protobuf \
    && docker-php-ext-install pdo pdo_pgsql pcntl \
    && rm -rf /tmp/pear

# Final Stage
FROM php:8.4-fpm-alpine

# Install runtime dependencies
RUN apk add --no-cache postgresql-libs nginx supervisor

# Copy extensions from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d

WORKDIR /var/www/html

# Copy application code first so artisan is available for composer hooks
COPY services/laravel .

# Composer installation
RUN php -r "copy('https://getcomposer.org/installer', 'cs.php');" \
    && php cs.php --install-dir=/usr/local/bin --filename=composer \
    && rm cs.php \
    && composer install --no-dev --no-interaction --optimize-autoloader

RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data storage bootstrap/cache

# Copy nginx and supervisor configs
COPY services/laravel/nginx.conf /etc/nginx/http.d/default.conf
COPY services/laravel/supervisord.conf /etc/supervisord.conf

EXPOSE 8000
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
