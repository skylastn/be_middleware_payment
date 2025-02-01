#!/bin/bash

# Cek versi PHP saat ini (dua digit pertama)
phpVersion=$(php -r 'echo trim(preg_replace("/^(\d+\.\d+).*/", "$1", PHP_VERSION));')
echo "PHP Version: $phpVersion"
phpVersionProject="8.2"

# Jika versi PHP tidak sama dengan 8.1, unlink versi saat ini dan link ke 8.1
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

# php artisan octane:frankenphp --workers 20 --port 8000
php artisan serve --port 2000
