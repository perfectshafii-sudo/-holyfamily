FROM php:8.2-apache

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy all project files to Apache default web root
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Expose port 80
EXPOSE 80
