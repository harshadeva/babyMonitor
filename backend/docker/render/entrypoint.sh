#!/bin/sh
set -e

: "${PORT:=10000}"
export PORT

envsubst '${PORT}' < /etc/nginx/templates/nginx.conf.template > /etc/nginx/http.d/default.conf

php artisan config:clear
php artisan migrate --force

php artisan config:cache
php artisan route:cache

php-fpm -D

exec nginx -g 'daemon off;'
