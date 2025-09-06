# Use official PHP image with required extensions
FROM php:8.4-fpm

# Arguments for user
ARG user=laravel
ARG uid=1000

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev zip unzip nodejs npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql mbstring exif pcntl bcmath opcache \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

# Create system user
RUN useradd -G www-data,root -u $uid -d /home/$user $user \
    && mkdir -p /home/$user/.composer \
    && chown -R $user:$user /home/$user

WORKDIR /var/www

# Copy files
COPY . .

# Install PHP deps
RUN composer install --optimize-autoloader --no-dev

# Build frontend
RUN npm install && npm run build

# Permissions
RUN chown -R $user:$user /var/www

USER $user

EXPOSE 9000

CMD ["php-fpm"]
