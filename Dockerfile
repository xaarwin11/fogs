FROM php:8.2-apache
COPY public/ /var/www/html/
RUN chmod -R 755 /var/www/html
