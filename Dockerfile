# Image production Laravel untuk Render (nginx + php-fpm dalam satu container).
# Render tidak punya runtime PHP bawaan, jadi Language di dashboard harus Docker.

# ── Tahap 1: dependensi PHP ────────────────────────────────────────────────
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
        --prefer-dist --no-interaction --no-progress
COPY . .
RUN composer dump-autoload --no-dev --no-scripts --optimize

# ── Tahap 2: image yang dijalankan ─────────────────────────────────────────
FROM php:8.3-fpm-alpine

# Ekstensi yang dipakai aplikasi: pdo_mysql (database), gd (gambar di PDF
# dompdf), zip/bcmath/exif (pendukung), opcache (kecepatan).
RUN apk add --no-cache \
        nginx gettext libpng libjpeg-turbo freetype libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd zip bcmath exif opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/*

WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

# Daftar service provider paket di-cache saat build (di sini semua ekstensi
# sudah tersedia, tidak seperti di image composer).
RUN php artisan package:discover --ansi

COPY docker/php.ini             /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/entrypoint.sh       /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions \
                storage/framework/views storage/logs storage/app/public \
                bootstrap/cache /var/lib/nginx/tmp \
    && chown -R www-data:www-data storage bootstrap/cache /var/lib/nginx

# Render menyuntikkan PORT; nilai ini hanya cadangan untuk uji coba lokal.
ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["entrypoint"]
