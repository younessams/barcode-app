# Android + Termux

This guide runs the existing Laravel barcode application locally on the phone. The phone does not need the PC after the project has been copied and its Composer dependencies have been installed.

## 1. Install Termux

Install Termux from [F-Droid](https://f-droid.org/packages/com.termux/) or the official [Termux GitHub releases](https://github.com/termux/termux-app/releases). Do not combine installations from different sources because their update signatures differ.

## 2. First-time setup

Open Termux and run:

```sh
pkg update && pkg upgrade
pkg install php php-gd composer unzip
termux-setup-storage
```

Allow the Android storage permission when prompted. Install `git` only if you plan to transfer or update the project with Git:

```sh
pkg install git
```

The current Termux package names are `php`, `php-gd`, `composer`, and `unzip`. The `php` package supplies the PHP runtime and its bundled extensions; `php-gd` supplies the GD extension required by PhpSpreadsheet. Verify the actual phone installation before installing the project:

```sh
php -v
php -m
```

The project requires PHP `>=8.2`. Laravel 12 needs ctype, curl, DOM, fileinfo, filter, hash, mbstring, OpenSSL, PCRE, PDO, session, tokenizer, and XML support. PhpSpreadsheet additionally requires GD, iconv, libxml, SimpleXML, XMLReader, XMLWriter, ZIP, and zlib. TCPDF requires cURL. Termux's PHP package provides the PHP extensions; Composer will verify the platform after installation.

## 3. Copy the project

On the PC, make a copy of the project for transfer. Keep `public/build`, `composer.json`, and `composer.lock`. Do not transfer the PC `.env`.

The runnable copy should live inside Termux home, where it is writable and does not depend on Android shared-storage permissions:

```sh
mkdir -p ~/barcode-app
cp -r ~/storage/downloads/barcode-app/. ~/barcode-app/
cd ~/barcode-app
```

The `~/storage/downloads` path is available after `termux-setup-storage`. Adjust the source path if the copied folder is elsewhere in shared storage.

Do not run the application directly from `~/storage/shared`; use shared storage for transferring Excel files and receiving PDFs, and keep the runnable project at `~/barcode-app`.

## 4. Install PHP dependencies

Run Composer once inside Termux. This creates an Android-compatible `vendor` directory from the lock file:

```sh
cd ~/barcode-app
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
```

Do not copy the Windows `vendor` directory and do not use `--ignore-platform-reqs`. Node/npm is not required on the phone because the built files in `public/build` are already part of the transfer.

## 5. Configure the local environment

Create the Android `.env` and generate a phone-local application key:

```sh
cd ~/barcode-app
cp .env.android.example .env
php artisan key:generate
```

The Android example uses file sessions, file cache, synchronous queues, and local storage. No database is required by the barcode workflow. Never copy or commit a real `APP_KEY`.

## 6. Daily use

Start the application:

```sh
cd ~/barcode-app
bash scripts/start-android.sh
```

Open the Android browser at [http://127.0.0.1:8000](http://127.0.0.1:8000). Use the browser's file picker to select an `.xls` or `.xlsx` file from Android storage, such as `Downloads`. Generate the PDF normally, then use the result's download/open action. Android browsers normally save downloaded PDFs in the device's `Download` folder.

Stop the server with `Ctrl-C` in the Termux session. The script checks PHP, Composer's autoloader, `.env`, and Laravel's writable directories before starting; it does not install packages automatically or clear application data.

## 7. What to transfer

Required: application source, `composer.json`, `composer.lock`, and the pre-built `public/build` directory.

Not required for the phone copy:

- `node_modules`
- `.git`
- `tests` (unless you want to run the test suite on the phone)
- local logs, cache files, and generated PDFs under `storage`
- Docker, Render, and other deployment files when this is only a local Android copy

Do not delete those items from the desktop project just to prepare the copy. They are only omitted from the transfer copy.

## Sources

- [Laravel 12 server requirements](https://laravel.com/docs/12.x/deployment#server-requirements)
- [Termux package sources](https://github.com/termux/termux-packages/tree/master/packages)
- [Termux PHP package](https://github.com/termux/termux-packages/tree/master/packages/php)
- [PhpSpreadsheet requirements](https://phpspreadsheet.readthedocs.io/en/latest/#software-requirements)
