#!/bin/bash
echo "=== Starting Marche Food ==="
echo "PHP version: $(php -v | head -1)"

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

# Start queue worker in the background
php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> /dev/null 2>&1 &

echo "=== Scheduler and queue worker started, launching Apache ==="
exec apache2-foreground
