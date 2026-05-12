FROM php:8.2-apache

# Enable Apache rewrite module (important for APIs and clean URLs)
RUN a2enmod rewrite

# Install required extensions for MySQL (common in billing systems)
RUN docker-php-ext-install pdo pdo_mysql

# Copy project files into Apache web root
COPY . /var/www/html/

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
