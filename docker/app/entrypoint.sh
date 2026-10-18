#!/bin/sh
set -e

uses_redis() {
    [ "${QUEUE_CONNECTION:-}" = "redis" ] || [ "${CACHE_STORE:-}" = "redis" ] || [ "${SESSION_DRIVER:-}" = "redis" ]
}

if uses_redis; then
    if ! php -r '
        $redis = new Redis();
        try {
            $redis->connect(getenv("REDIS_HOST") ?: "redis", (int) (getenv("REDIS_PORT") ?: 6379), 3);
            $password = getenv("REDIS_PASSWORD");
            if ($password !== false && $password !== "" && $password !== "null") {
                $redis->auth($password);
            }
            exit($redis->ping() ? 0 : 1);
        } catch (Throwable $exception) {
            exit(1);
        }
    '; then
        echo "PulseCheck is configured to use Redis (QUEUE_CONNECTION, CACHE_STORE or SESSION_DRIVER is \"redis\")," >&2
        echo "but Redis is not reachable at ${REDIS_HOST:-redis}:${REDIS_PORT:-6379} or rejected the password." >&2
        echo "Start it with: docker compose --profile redis up -d   (and set REDIS_PASSWORD in .env)" >&2
        echo "Or switch the drivers back to \"database\" in .env." >&2
        exit 1
    fi
fi

php artisan config:cache --no-interaction > /dev/null
php artisan route:cache --no-interaction > /dev/null
php artisan event:cache --no-interaction > /dev/null

if [ "${1:-}" = "php-fpm" ]; then
    php artisan pulsecheck:setup-token --no-interaction || true
fi

exec "$@"
