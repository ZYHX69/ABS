FROM php:8.1-apache

RUN docker-php-ext-install pdo_mysql
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy application
COPY . .

# Install Ratchet
RUN composer require cboden/ratchet

# Expose ports
EXPOSE 80 8080

# Start Apache and WebSocket server (using supervisord)
RUN apt-get update && apt-get install -y supervisor
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD ["/usr/bin/supervisord"]