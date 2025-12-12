# Use official PHP image
FROM php:8.2-apache

# Copy your app into the container
COPY . /var/www/html/

# Expose port 80
EXPOSE 80