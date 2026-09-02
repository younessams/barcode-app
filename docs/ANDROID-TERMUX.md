# Android + Termux

This runs the existing Laravel barcode application locally on an Android phone. The phone does not need the PC after setup. The approved barcode geometry, PDF pipeline, Excel parsing, and desktop behavior are unchanged.

## QUICK INSTALL FOR COWORKERS

### 1. Install Termux

The tested Play Store Termux installation is acceptable. Install Termux from the [Google Play listing](https://play.google.com/store/apps/details?id=com.termux). Do not combine installations from different sources on the same phone.

### 2. Paste this one setup block

Open Termux and paste the whole block:

```sh
pkg update -y
pkg install -y git php php-gd composer unzip nodejs-lts
termux-setup-storage

if [ -e "$HOME/barcode-app" ]; then
    cd "$HOME/barcode-app"
else
    cd "$HOME"
    git clone https://github.com/younessams/barcode-app.git barcode-app
    cd "$HOME/barcode-app"
fi

bash scripts/setup-android.sh
```

The setup checks that it is running inside Termux, verifies PHP GD, installs the locked Composer dependencies, builds the frontend assets, creates `.env` only when missing, generates its key only once, and creates the global `start-app` command.

### 3. Daily use

```sh
start-app
```

Open this URL in the Android browser:

```text
http://127.0.0.1:8000
```

Use the browser file picker to choose an `.xls` or `.xlsx` file from `Downloads` or another Android storage location. Generate the PDF and use the result's download/open action. PDFs normally appear in the browser's `Download` folder. Press `Ctrl+C` in Termux to stop the server.

## Already installed

If `~/barcode-app` already exists, do not clone into it and do not create `~/barcode-app/barcode-app`. Confirm the existing copy, then run:

```sh
cd "$HOME/barcode-app"
bash scripts/setup-android.sh
```

The setup script is safe to rerun. It does not delete directories, overwrite an existing `.env`, or regenerate an existing `APP_KEY`. If the existing copy predates the Android setup files, copy the current project into `~/barcode-app` or use a separate clean directory and then run the script there.

## What the setup installs

The exact Termux packages used by the tested setup are:

- `php` (PHP CLI and the PHP extensions supplied by the Termux package)
- `php-gd` (GD required by PhpSpreadsheet)
- `composer`
- `unzip`
- `nodejs-lts` (needed once for `npm ci` and `npm run build`)
- `git` (used by the coworker clone block)

The project requires PHP `>=8.2`. PhpSpreadsheet also requires GD, XML-related extensions, ZIP, zlib, iconv, and file handling support. TCPDF requires cURL. The setup runs `composer check-platform-reqs --no-dev` and stops if the phone does not satisfy the locked dependencies.

## Manual or troubleshooting steps

Keep the runnable project inside Termux home:

```sh
~/barcode-app
```

Use shared storage only for transfer and user files. `termux-setup-storage` creates paths such as `~/storage/downloads`; it does not move the runnable project there.

The setup creates `php-termux.ini` with the tested Android PHP configuration:

```ini
[PHP]
opcache.enable=0
opcache.enable_cli=0
opcache.file_cache_fallback=1
sys_temp_dir=/data/data/com.termux/files/usr/tmp
```

Android startup intentionally uses the direct PHP built-in server with Laravel's router. It does not use `php artisan serve`:

```sh
cd ~/barcode-app/public
php -c ../php-termux.ini \
  -S 127.0.0.1:8000 \
  ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```

If a required command is missing, install the packages again with:

```sh
pkg install -y git php php-gd composer unzip nodejs-lts
```

Do not use `--ignore-platform-reqs`, do not copy the Windows `vendor` directory, and do not copy the PC `.env`. `public/build` is generated during setup and is used by the browser at runtime.

## Transfer contents

The clean phone copy needs the application source, `composer.json`, `composer.lock`, `package.json`, and `package-lock.json`. The setup block builds `public/build` itself, so Node/npm is only needed during setup, not for daily use.

Do not transfer these items unless specifically needed:

- `.git`
- `node_modules`
- the PC `.env`
- tests
- local logs, cache files, or generated PDFs under `storage`
- Docker, Render, and other deployment files

Do not delete those items from the desktop project to make a transfer copy smaller.

## Sources

- [Laravel 12 server requirements](https://laravel.com/docs/12.x/deployment#server-requirements)
- [Termux package sources](https://github.com/termux/termux-packages/tree/master/packages)
- [Termux PHP package](https://github.com/termux/termux-packages/tree/master/packages/php)
- [PhpSpreadsheet requirements](https://phpspreadsheet.readthedocs.io/en/latest/#software-requirements)
