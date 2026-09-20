#!/bin/sh
# Dijalankan setiap container Render menyala — termasuk setiap kali instance
# free tier bangun dari tidur. Karena itu semua langkah di sini harus aman
# diulang (idempotent) dan cepat.
set -e

: "${PORT:=10000}"
export PORT

cd /var/www/html

# 1. Sertifikat CA MySQL. Apa pun bentuk yang diberikan, hasil akhirnya satu
#    berkas di /tmp yang bisa dibaca www-data — pengguna yang menjalankan worker
#    php-fpm. Skrip ini sendiri berjalan sebagai root, jadi tanpa langkah ini
#    sebuah berkas milik root akan lolos saat `migrate` tapi gagal saat halaman
#    diakses, dengan pesan "failed loading cafile stream".
case "${MYSQL_ATTR_SSL_CA:-}" in
    '')
        ;;
    *"BEGIN CERTIFICATE"*)
        # Isi PEM ditempel langsung sebagai environment variable. Baris base64-nya
        # dirangkai ulang 64 karakter supaya tetap sah walaupun kotak isian Render
        # menghilangkan pergantian barisnya.
        ca_body=$(printf '%s' "$MYSQL_ATTR_SSL_CA" | sed -e 's/-----BEGIN CERTIFICATE-----//g' -e 's/-----END CERTIFICATE-----//g' | tr -d '[:space:]' | fold -w 64)
        printf '%s\n%s\n%s\n' '-----BEGIN CERTIFICATE-----' "$ca_body" '-----END CERTIFICATE-----' > /tmp/mysql-ca.pem
        chmod 644 /tmp/mysql-ca.pem
        export MYSQL_ATTR_SSL_CA=/tmp/mysql-ca.pem
        echo "==> isi sertifikat CA MySQL ditulis ke /tmp/mysql-ca.pem"
        ;;
    *)
        # Lokasi berkas, mis. Secret File Render di /etc/secrets/ca.pem.
        if [ -f "$MYSQL_ATTR_SSL_CA" ]; then
            cp "$MYSQL_ATTR_SSL_CA" /tmp/mysql-ca.pem
            chmod 644 /tmp/mysql-ca.pem
            export MYSQL_ATTR_SSL_CA=/tmp/mysql-ca.pem
            echo "==> sertifikat CA MySQL disalin ke /tmp/mysql-ca.pem agar terbaca www-data"
        else
            echo "!! MYSQL_ATTR_SSL_CA menunjuk berkas yang tidak ada: ${MYSQL_ATTR_SSL_CA}"
        fi
        ;;
esac

# 2. Port Render dimasukkan ke konfigurasi nginx.
envsubst '${PORT}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf

# 3. Direktori tulis. Filesystem container selalu kosong lagi saat menyala.
mkdir -p storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs storage/app/public bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 4. public/storage -> storage/app/public, supaya foto karya bisa diakses.
php artisan storage:link --force >/dev/null 2>&1 || true

# 5. Migrasi. Matikan lewat RUN_MIGRATIONS=false kalau ingin menjalankannya manual.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "==> php artisan migrate --force"
    php artisan migrate --force
fi

# 6. Jalan sekali-pakai. Free tier Render tidak punya akses Shell/SSH, jadi
#    perintah artisan satu kali (seeder, impor, perbaikan data) dijalankan
#    lewat environment variable: isi, deploy, lalu kosongkan lagi.
if [ -n "${SEED_CLASS:-}" ]; then
    echo "==> php artisan db:seed --class=${SEED_CLASS}"
    php artisan db:seed --force --class="${SEED_CLASS}"
fi

if [ -n "${STARTUP_COMMAND:-}" ]; then
    echo "==> php artisan ${STARTUP_COMMAND}"
    # shellcheck disable=SC2086
    php artisan ${STARTUP_COMMAND}
fi

# 7. Cache config/route/view. Dibuat saat runtime, bukan saat build, karena
#    nilainya berasal dari environment variable Render.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 8. Penjadwal Laravel (students:suspend-overdue). Hanya berguna kalau instance
#    dijaga tetap hidup oleh ping berkala — lihat docs/deploy-render.md.
if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
    echo "==> scheduler aktif (php artisan schedule:work)"
    php artisan schedule:work >/dev/null 2>&1 &
fi

# 9. Berkas cache tadi dibuat sebagai root; kembalikan kepemilikannya supaya
#    proses php-fpm (www-data) tetap bisa menulis log dan view baru.
chown -R www-data:www-data storage bootstrap/cache

# 10. php-fpm di latar belakang, nginx sebagai proses utama container.
php-fpm -D
exec nginx -g 'daemon off;'
