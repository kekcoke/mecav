# ── Stage 1: Node.js asset builder ───────────────────────────────────────────
FROM node:20-alpine AS node-builder

WORKDIR /app

# Copy only manifests first for better layer caching
COPY services/laravel/package.json services/laravel/package-lock.json* ./
COPY services/laravel/vite.config.js ./
# index.html is Vite's entry module — required even in Laravel projects that use
# a plain Vite config rather than laravel-vite-plugin.
COPY services/laravel/index.html ./
COPY services/laravel/resources ./resources
COPY services/laravel/public ./public

# Use 'ci' (not 'install') to reproduce the exact lockfile in CI/CD
RUN npm ci && npm run build


# ── Stage 2: PHP extension builder ────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS builder

# All build-time deps: compiler toolchain + postgres dev headers
RUN apk add --no-cache postgresql-dev linux-headers $PHPIZE_DEPS

# Install gRPC and Protobuf via pecl *separately* so each failure surfaces
# explicitly — combined `pecl install grpc protobuf` can silently swallow a
# build failure for the second package while still exiting 0.
# protobuf-dev provides the headers required to compile the protobuf extension.
RUN apk add --no-cache protobuf-dev \
    && export MAKEFLAGS="-j$(nproc)" \
    && php -d memory_limit=-1 "$(which pecl)" install grpc \
    && php -d memory_limit=-1 "$(which pecl)" install protobuf \
    && rm -rf /tmp/pear /tmp/pear-*

# Compile pdo, pdo_pgsql, pcntl here where postgresql-dev and $PHPIZE_DEPS
# are present. The final stage has no compiler so these cannot be built there.
RUN docker-php-ext-install -j"$(nproc)" pdo pdo_pgsql pcntl

# ── Export .so files to a stable, path-independent staging dir ────────────────
# php -r 'echo ini_get("extension_dir")' resolves the real path at runtime,
# avoiding the fragile hardcoded no-debug-non-zts-YYYYMMDD directory name.
RUN ext_dir="$(php -r 'echo ini_get("extension_dir");')" \
    && mkdir -p /tmp/php-extensions \
    && cp \
        "${ext_dir}/grpc.so" \
        "${ext_dir}/protobuf.so" \
        "${ext_dir}/pdo.so" \
        "${ext_dir}/pdo_pgsql.so" \
        "${ext_dir}/pcntl.so" \
       /tmp/php-extensions/


# ── Stage 3: Final runtime image ──────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# Runtime-only OS packages — no compiler, no dev headers
RUN apk add --no-cache libpq nginx supervisor

# Copy the staged .so files into the extension directory that *this* PHP build
# reports at runtime (same dynamic resolution, no hardcoded path).
COPY --from=builder /tmp/php-extensions/ /tmp/php-extensions/
RUN ext_dir="$(php -r 'echo ini_get("extension_dir");')" \
    && cp /tmp/php-extensions/*.so "${ext_dir}/" \
    && rm -rf /tmp/php-extensions

# Enable extensions via ini only — docker-php-ext-enable writes the ini files.
# No recompilation happens here; the .so files are already in place.
RUN docker-php-ext-enable grpc protobuf pdo pdo_pgsql pcntl

# Use the official Composer image to avoid a runtime curl-to-internet call
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

# Copy application source before composer so artisan hooks work
COPY services/laravel .

# `composer install` (not `update`) reproduces the committed lockfile exactly
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
    && rm -rf ~/.composer

# Copy compiled front-end assets from Node builder
COPY --from=node-builder /app/public/build ./public/build

# Cache routes and views at build time — these are environment-independent.
# NOTE: php artisan config:cache requires APP_KEY and DB env vars, so it is
# intentionally deferred to the container entrypoint (see docker-entrypoint.sh).
RUN php artisan route:cache \
    && php artisan view:cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY services/laravel/nginx.conf /etc/nginx/http.d/default.conf
COPY services/laravel/supervisord.conf /etc/supervisord.conf
COPY services/laravel/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 8000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
