FROM php:8.1-apache

# Instalează extensiile MySQL
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Activează mod_rewrite
RUN a2enmod rewrite

WORKDIR /var/www/html