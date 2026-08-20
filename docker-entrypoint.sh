#!/bin/sh
set -e
cd /app

# توليد ملف .env بإعدادات قاعدة البيانات القادمة من docker-compose
cat > .env <<EOF
APP_NAME="Memos Press"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8000
LOG_CHANNEL=stack

DB_CONNECTION=mysql
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

SESSION_DRIVER=file
SESSION_SAME_SITE=strict
SESSION_SECURE_COOKIE=false
CACHE_STORE=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
SANCTUM_STATEFUL_DOMAINS=localhost:5173

ADMIN_PHONE=${ADMIN_PHONE}
ADMIN_PASSWORD=${ADMIN_PASSWORD}
EOF

php artisan key:generate --force

echo "Waiting for database ${DB_HOST}:${DB_PORT} ..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}','${DB_USERNAME}','${DB_PASSWORD}');" >/dev/null 2>&1; do
  echo "  ...still waiting for the database"
  sleep 3
done

echo "Running migrations + seed ..."
php artisan migrate:fresh --seed --force
php artisan storage:link || true

echo "Backend ready on http://0.0.0.0:8000"
exec php artisan serve --host=0.0.0.0 --port=8000
