FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    locales \
    libonig-dev \
    libxml2-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Install Laravel Reverb
RUN composer create-project laravel/reverb . --no-install
RUN composer install --no-dev --optimize-autoloader

# Create Reverb configuration
COPY reverb.conf /etc/supervisor/conf.d/reverb.conf

# Expose port
EXPOSE 8080

# Start Reverb server
CMD ["php", "artisan", "reverb:start"]
