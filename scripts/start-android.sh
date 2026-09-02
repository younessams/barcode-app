#!/data/data/com.termux/files/usr/bin/bash

set -eu

PROJECT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
cd "$PROJECT_DIR"

if ! command -v php >/dev/null 2>&1; then
    printf '%s\n' 'PHP is not installed. Run: pkg install php php-gd composer unzip' >&2
    exit 1
fi

if [ ! -f vendor/autoload.php ]; then
    printf '%s\n' 'Composer dependencies are missing. Run: composer install --no-dev --prefer-dist --optimize-autoloader' >&2
    exit 1
fi

if [ ! -f .env ]; then
    printf '%s\n' 'The .env file is missing. Copy .env.android.example to .env and run php artisan key:generate.' >&2
    exit 1
fi

mkdir -p \
    bootstrap/cache \
    storage/app/generated-labels \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

for directory in bootstrap/cache storage/app storage/framework storage/logs; do
    if [ ! -w "$directory" ]; then
        printf 'Directory is not writable: %s\n' "$PROJECT_DIR/$directory" >&2
        exit 1
    fi
done

exec php artisan serve --host=127.0.0.1 --port=8000
