# Utilise une image PHP officielle avec Apache
FROM php:8.2-apache

# Installer les dépendances nécessaires pour Laravel, y compris PostgreSQL
RUN apt-get update \
    && apt-get install -y \
        libxml2-dev \
        libzip-dev \
        unzip \
        git \
        libpq-dev \
        nano \
        ca-certificates \
    && update-ca-certificates \
    && docker-php-ext-install \
        pdo_mysql \
        pdo_pgsql \
        xml \
        zip \
        dom \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Télécharger Composer et l'installer globalement
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Configuration Composer pour les problèmes SSL
RUN composer config -g repos.packagist composer https://packagist.org
RUN composer config -g secure-http false

# Définir le répertoire de travail dans le conteneur
WORKDIR /var/www/html

# Copier les fichiers de l'application Laravel dans le conteneur
COPY . .

# Installer les dépendances PHP via Composer avec gestion des erreurs SSL
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs \
    || composer install --no-dev --optimize-autoloader --ignore-platform-reqs --disable-tls \
    || (composer config -g secure-http false && composer install --no-dev --optimize-autoloader --ignore-platform-reqs)

# Définir les permissions nécessaires pour Laravel
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

# Configurer Apache pour utiliser /var/www/html/public comme document root
RUN sed -i -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

# Configuration Apache pour Laravel
RUN echo '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Exposer le port HTTP du conteneur
EXPOSE 80

# Configurer l'entrée de commande pour Apache
CMD ["apache2-foreground"]
