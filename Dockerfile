# Part-I image: Apache + PHP web server with the application code baked in.
FROM php:8.2-apache

# PDO MySQL driver for the database integration.
RUN docker-php-ext-install pdo pdo_mysql \
 && a2enmod rewrite

# Application code is copied into the image (Part-I requirement).
COPY app/ /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
