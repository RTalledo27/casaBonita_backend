# Etapa base PHP + Apache
FROM php:8.2-apache

# Trabajamos en /var/www/html
WORKDIR /var/www/html

# Dependencias del sistema
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip \
    libzip-dev libicu-dev libpq-dev \
 && docker-php-ext-configure intl \
 && docker-php-ext-install pdo_mysql pdo_pgsql mbstring exif pcntl bcmath gd zip intl \
 && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Composer (copiamos binario desde imagen oficial)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# ---- Capa de dependencias PHP (mejor cache) ----
# Copiamos solo composer.* primero para cachear composer install
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# ---- Copiamos el resto de la app ----
COPY . .

# Permisos para storage y cache
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

# DocumentRoot a /public
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Silenciar warning de ServerName (opcional)
RUN printf "\nServerName localhost\n" >> /etc/apache2/apache2.conf

# VirtualHost (por si el de arriba se sobreescribe)
RUN printf "<VirtualHost *:80>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    ErrorLog \${APACHE_LOG_DIR}/error.log\n\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>\n" > /etc/apache2/sites-available/000-default.conf

# Copiamos entrypoint y lo hacemos ejecutable
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

# Usamos el entrypoint (corre migraciones y arranca Apache)
CMD ["bash","-lc","/usr/local/bin/entrypoint.sh"]
