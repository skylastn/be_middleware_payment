# Gunakan gambar dasar dari frankenphp
FROM dunglas/frankenphp

# Install ekstensi yang diperlukan untuk Laravel
RUN install-php-extensions pcntl mbstring bcmath curl openssl gd pdo_mysql

# Tambahkan paket tambahan yang dibutuhkan
RUN apt-get update && apt-get install -y procps supervisor

# Salin file aplikasi ke dalam container
COPY . /app

# Tentukan direktori kerja
WORKDIR /app

# Jalankan supervisord untuk Horizon
CMD ["php", "artisan", "octane:frankenphp", '--workers', '20', '--port', '8000']
