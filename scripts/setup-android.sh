#!/data/data/com.termux/files/usr/bin/bash

set -eu

if [ -z "${TERMUX_VERSION:-}" ] || [ -z "${PREFIX:-}" ] || [ ! -d "$PREFIX" ]; then
    printf '%s\n' 'This setup script must be run inside Termux.' >&2
    exit 1
fi

PROJECT_DIR="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"

if printf '%s' "$PROJECT_DIR" | grep -q "'"; then
    printf '%s\n' "The project path contains an unsupported single quote: $PROJECT_DIR" >&2
    exit 1
fi

require_command() {
    if ! command -v "$1" >/dev/null 2>&1; then
        printf 'Required command is missing: %s\n' "$1" >&2
        printf '%s\n' 'Install the Termux packages first: pkg install git php php-gd composer unzip nodejs-lts' >&2
        exit 1
    fi
}

for command_name in php composer node npm git; do
    require_command "$command_name"
done

if ! php --ri gd 2>/dev/null | grep -qi 'GD Support => enabled'; then
    printf '%s\n' 'PHP GD is not enabled. Install or repair the Termux php-gd package.' >&2
    exit 1
fi

cd "$PROJECT_DIR"

if [ ! -f composer.json ] || [ ! -f composer.lock ]; then
    printf '%s\n' 'composer.json and composer.lock are required in the project root.' >&2
    exit 1
fi

if [ ! -f package.json ] || [ ! -f package-lock.json ]; then
    printf '%s\n' 'package.json and package-lock.json are required in the project root.' >&2
    exit 1
fi

cat > php-termux.ini <<'INI'
[PHP]
opcache.enable=0
opcache.enable_cli=0
opcache.file_cache_fallback=1
sys_temp_dir=/data/data/com.termux/files/usr/tmp
INI

mkdir -p \
    bootstrap/cache \
    storage/app/generated-labels \
    storage/app/private \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    "$PREFIX/tmp"

for directory in bootstrap/cache storage/app storage/framework storage/logs; do
    if [ ! -w "$directory" ]; then
        printf 'Directory is not writable: %s\n' "$PROJECT_DIR/$directory" >&2
        exit 1
    fi
done

printf '%s\n' 'Installing frontend dependencies...'
npm ci
npm run build

printf '%s\n' 'Installing PHP dependencies...'
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev

if [ ! -f .env ]; then
    if [ ! -f .env.android.example ]; then
        printf '%s\n' '.env.android.example is missing.' >&2
        exit 1
    fi

    cp .env.android.example .env
    php -c php-termux.ini artisan key:generate
fi

cat > "$PREFIX/bin/start-app" <<EOF
#!/data/data/com.termux/files/usr/bin/bash

set -eu

PROJECT_DIR='$PROJECT_DIR'

if ! command -v php >/dev/null 2>&1; then
    printf '%s\\n' 'PHP is not installed. Run: pkg install php php-gd.' >&2
    exit 1
fi

if [ ! -f "\$PROJECT_DIR/vendor/autoload.php" ]; then
    printf '%s\\n' 'Composer dependencies are missing. Run: bash \"\$PROJECT_DIR/scripts/setup-android.sh\".' >&2
    exit 1
fi

if [ ! -f "\$PROJECT_DIR/.env" ]; then
    printf '%s\\n' 'The .env file is missing. Run the Android setup script first.' >&2
    exit 1
fi

if [ ! -f "\$PROJECT_DIR/public/build/manifest.json" ]; then
    printf '%s\\n' 'Built frontend assets are missing. Run the Android setup script first.' >&2
    exit 1
fi

if [ ! -f "\$PROJECT_DIR/php-termux.ini" ]; then
    printf '%s\\n' 'php-termux.ini is missing. Run the Android setup script first.' >&2
    exit 1
fi

cd "\$PROJECT_DIR/public"

printf '%s\\n' ''
printf '%s\\n' 'Barcode App'
printf '%s\\n' 'Open in browser:'
printf '%s\\n' 'http://127.0.0.1:8000'
printf '%s\\n' ''
printf '%s\\n' 'Press Ctrl+C to stop.'
printf '%s\\n' ''

exec php -c ../php-termux.ini -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
EOF

chmod +x "$PREFIX/bin/start-app"

printf '%s\n' ''
printf '%s\n' '================================'
printf '%s\n' 'Barcode App installed successfully'
printf '%s\n' '================================'
printf '%s\n' ''
printf '%s\n' 'Daily use:'
printf '%s\n' '1. Open Termux'
printf '%s\n' '2. Type: start-app'
printf '%s\n' '3. Open: http://127.0.0.1:8000'
