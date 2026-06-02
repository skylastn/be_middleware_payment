#!/bin/bash

# Check the current PHP version (major.minor)
phpVersion=$(php -r 'echo trim(preg_replace("/^(\d+\.\d+).*/", "$1", PHP_VERSION));')
echo "PHP Version: $phpVersion"
phpVersionProject="8.4"

# If the PHP version does not match the project version, unlink the current version and link the project version
if [ "$phpVersion" != "$phpVersionProject" ]; then
    echo "Unlinking php@$phpVersion"
    brew unlink php@$phpVersion || (
        echo "Failed to unlink php@$phpVersion"
        exit 1
    )
    echo "Linking php@$phpVersionProject"
    brew link php@$phpVersionProject || (
        echo "Failed to link php@$phpVersionProject"
        exit 1
    )
else
    echo "PHP version is already $phpVersionProject"
fi

# php artisan octane:frankenphp --caddyfile=./Caddyfile --workers 20 --port 8000
# (the custom Caddyfile adds CORS headers for /build/* CSS/JS etc.)
php artisan serve --port 2000
