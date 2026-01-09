# Use official PHP Apache image
FROM php:8.2-apache

# Install system dependencies required for MySQL extensions
RUN apt-get update && apt-get install -y \
    default-mysql-client \
    libzip-dev \
    unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache rewrite module
RUN a2enmod rewrite

# Clean up
RUN apt-get clean && rm -rf /var/lib/apt/lists/*
