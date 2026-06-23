#!/bin/bash
echo "=== Starting Marche Food ==="
echo "PHP version: $(php -v | head -1)"
echo "APP_ENV: ${APP_ENV}"
echo "DB_CONNECTION: ${DB_CONNECTION}"
echo "DB_HOST: ${DB_HOST}"
echo "DB_DATABASE: ${DB_DATABASE}"

php /var/www/html/artisan migrate --force
if [ $? -ne 0 ]; then
    echo "=== MIGRATION FAILED ==="
    cat /var/www/html/storage/logs/laravel.log 2>/dev/null | tail -30
    exit 1
fi

echo "=== Migration complete ==="

# Start Laravel scheduler in the background (runs every minute)
(while true; do
    php /var/www/html/artisan schedule:run >> /dev/null 2>&1
    sleep 60
done) &

echo "=== Scheduler started, launching Apache ==="
exec apache2-foreground
