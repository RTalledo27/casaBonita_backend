# Use official PHP 8.2 image with Apache
FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# System deps
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    libzip-dev libicu-dev libpq-dev \
 && docker-php-ext-configure intl \
 && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip intl \
 && rm -rf /var/lib/apt/lists/*

# Apache mods
RUN a2enmod rewrite
RUN printf "\nServerName localhost\n" >> /etc/apache2/apache2.conf

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- Copiamos la app primero (como en tu versión que funcionaba) ---
COPY . /var/www/html
# No dupliques COPY; mejor ajustamos permisos explícitos
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# Instalar dependencias PHP
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
# Re-optimiza autoload por si algo cambió
RUN composer dump-autoload --no-dev --optimize

# DocumentRoot a /public (vhost)
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf
RUN printf "<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n" > /etc/apache2/sites-available/000-default.conf

# Entrypoint: migraciones + caches + arrancar Apache
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
# Normaliza fin de línea por si guardaste CRLF en Windows y hazlo ejecutable
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
CMD ["bash","-lc","/usr/local/bin/entrypoint.sh"]
