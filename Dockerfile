FROM php:8.2-apache

# Install mysqli (needed for MySQL access)
RUN docker-php-ext-install mysqli

# Ensure exactly one MPM module is enabled (base image sometimes ends up
# with more than one enabled, which crashes Apache on startup)
RUN a2dismod mpm_event mpm_worker 2>/dev/null; a2enmod mpm_prefork

# Enable Apache mod_rewrite (harmless even if unused, useful if routes are added later)
RUN a2enmod rewrite

# Copy the whole app (static frontend + api/) into Apache's document root
COPY . /var/www/html/

# Make sure runtime-writable directories exist and are writable by the
# web server user, since the PHP code writes uploads and logs here.
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs

# Configure Apache to use the dynamic PORT variable provided by Railway
RUN sed -i 's/Listen 80/Listen ${PORT}/g' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:${PORT}>/g' /etc/apache2/sites-available/000-default.conf \
    && echo "export PORT=\${PORT:-8080}" >> /etc/apache2/envvars

# Railway injects its own $PORT at runtime. Default to 8080.
ENV PORT=8080
EXPOSE 8080

CMD ["apache2-foreground"]
