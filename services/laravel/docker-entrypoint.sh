
#!/bin/sh
set -e

# php artisan config:cache needs APP_KEY and DB_* at runtime — run it here,
# not at image build time, so environment variables are properly injected.
php artisan config:cache

# Run any outstanding database migrations automatically on each deploy.
# For zero-downtime deployments replace this with a separate migration Job
# (the CI/CD pipeline already does this via kubectl).
# php artisan migrate --force

exec "$@"
