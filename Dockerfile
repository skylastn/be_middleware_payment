# Use the FrankenPHP base image with PHP 8.4
FROM dunglas/frankenphp:php8.4

# Install the PHP extensions required by Laravel
RUN install-php-extensions pcntl mbstring bcmath curl openssl gd pdo_mysql

# Add required system packages
RUN apt-get update && apt-get install -y procps supervisor

# Copy the application files into the container
COPY . /app

# Set the working directory
WORKDIR /app

# Run Laravel Octane with FrankenPHP
CMD ["php", "artisan", "octane:frankenphp", '--workers', '5', '--port', '8000']
