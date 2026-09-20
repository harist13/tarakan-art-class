#!/bin/sh
# Dijalankan setiap container Render menyala — termasuk setiap kali instance
# free tier bangun dari tidur. Karena itu semua langkah di sini harus aman
# diulang (idempotent) dan cepat.
set -e

: "${PORT:=10000}"
export PORT

cd /var/www/html

# 1. Port Render dimasukkan ke konfigurasi nginx.
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# 2. Direktori tulis. Filesystem container selalu kosong lagi saat menyala.
mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 3. public/storage -> storage/app/public, supaya foto karya bisa diakses.
php artisan storage:link --force >/dev/null 2>&1 || true

# 4. Migrasi. Matikan lewat RUN_MIGRATIONS=false kalau ingin menjalankannya manual.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "==> php artisan migrate --force"
    php artisan migrate --force
fi

# 4b. Jalan sekali-pakai. Free tier Render tidak punya akses Shell/SSH, jadi
#     perintah artisan satu kali (seeder, impor, perbaikan data) dijalankan
#     lewat environment variable: isi, deploy, lalu kosongkan lagi.
if [ -n "${SEED_CLASS:-}" ]; then
    echo "==> php artisan db:seed --class=${SEED_CLASS}"
    php artisan db:seed --force --class="${SEED_CLASS}"
fi

if [ -n "${STARTUP_COMMAND:-}" ]; then
    echo "==> php artisan ${STARTUP_COMMAND}"
    # shellcheck disable=SC2086
    php artisan ${STARTUP_COMMAND}
fi

# 5. Cache config/route/view. Dibuat saat runtime, bukan saat build, karena
#    nilainya berasal dari environment variable Render.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. Penjadwal Laravel (students:suspend-overdue). Hanya berguna kalau instance
#    dijaga tetap hidup oleh ping berkala — lihat docs/deploy-render.md.
if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
    echo "==> scheduler aktif (php artisan schedule:work)"
    php artisan schedule:work >/dev/null 2>&1 &
fi

# 7. Berkas cache tadi dibuat sebagai root; kembalikan kepemilikannya supaya
#    proses php-fpm (www-data) tetap bisa menulis log dan view baru.
chown -R www-data:www-data storage bootstrap/cache

# 8. php-fpm di latar belakang, nginx sebagai proses utama container.
php-fpm -D
exec nginx -g 'daemon off;'
