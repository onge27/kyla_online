FROM php:8.2-apache

# Install mysqli + PDO MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite (optional but common)
RUN a2enmod rewrite