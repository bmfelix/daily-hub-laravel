#!/bin/sh
set -e
# mod_php needs the prefork MPM. Apache will not start with more than one MPM enabled.
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec apache2-foreground
