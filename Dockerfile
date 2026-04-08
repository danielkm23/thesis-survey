FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql
RUN a2enmod rewrite

WORKDIR /var/www/html

# App code
COPY . /var/www/html/

# Render port
ENV PORT=10000

# Serve app from /public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Ensure Apache listens on Render port
RUN sed -ri -e "s/Listen 80/Listen ${PORT}/g" /etc/apache2/ports.conf \
    && sed -ri -e "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/*.conf

# Explicit directory permissions for deployment
RUN printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n    DirectoryIndex index.php index.html\n</Directory>\n' > /etc/apache2/conf-available/app-permissions.conf \
    && a2enconf app-permissions

EXPOSE 10000

CMD ["apache2-foreground"]
