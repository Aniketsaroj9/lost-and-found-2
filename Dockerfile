FROM php:8.2-apache

# Install mysqli (needed for MySQL access)
RUN docker-php-ext-install mysqli

# Enable Apache mod_rewrite (harmless even if unused, useful if routes are added later)
RUN a2enmod rewrite

# Copy the whole app (static frontend + api/) into Apache's document root
COPY . /var/www/html/

# Make sure runtime-writable directories exist and are writable by the
# web server user, since the PHP code writes uploads and logs here.
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs

# Railway injects its own $PORT at runtime; Apache defaults to listening on
# 80, so we precisely rewrite just the port directives at container start.
ENV PORT=8080
EXPOSE 8080

CMD ["sh", "-c", "sed -i \"s/Listen 80/Listen ${PORT}/\" /etc/apache2/ports.conf && sed -i \"s/:80>/:${PORT}>/\" /etc/apache2/sites-available/000-default.conf && apache2-foreground"]
