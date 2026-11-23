# -----------------------------
# Base image: PHP-FPM 8.2
# -----------------------------
FROM php:8.2-fpm

# -----------------------------
# Install system dependencies
# -----------------------------
RUN apt-get update && apt-get install -y \
    nginx \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip gd \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# -----------------------------
# Install Composer
# -----------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -----------------------------
# Set working directory
# -----------------------------
WORKDIR /var/www/html

# -----------------------------
# Copy Laravel project files
# -----------------------------
COPY . .

# -----------------------------
# Install Laravel dependencies
# -----------------------------
RUN composer install --no-dev --optimize-autoloader

# -----------------------------
# Set permissions for storage & cache
# -----------------------------
RUN chown -R www-data:www-data storage bootstrap/cache

# -----------------------------
# Copy Nginx configuration
# -----------------------------
COPY docker/nginx.conf /etc/nginx/nginx.conf

# -----------------------------
# Expose port for Railway
# -----------------------------
EXPOSE 8080

# -----------------------------
# Start Script
# -----------------------------
# We move the logic to a script or a long CMD to handle 
# the volume permissions at runtime.
# -----------------------------
CMD sh -c " \
    echo '📂 Setting up storage...' && \
    # Ensure the public storage directory exists in the volume
    mkdir -p storage/app/public && \
    # Create the symlink if it doesn't exist
    php artisan storage:link --force || true && \
    # Fix permissions at runtime (required for Volumes)
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache && \
    \
    echo '🚀 Starting PHP-FPM...' && \
    php-fpm & \
    \
    echo '🚀 Starting Nginx...' && \
    nginx -g 'daemon off;' & \
    \
    echo '🚀 Starting Laravel Scheduler...' && \
    while true; do \
        php artisan schedule:run --no-interaction & \
        sleep 60; \
    done"