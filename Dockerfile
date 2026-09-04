FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite headers

# Install system dependencies + PHP extensions
RUN apt-get update && apt-get install -y \
        libpng-dev libjpeg62-turbo-dev libwebp-dev \
        libzip-dev libonig-dev curl unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install mysqli pdo_mysql mbstring gd zip opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP: sessions + opcache
RUN mkdir -p /tmp/php_sessions && chmod 1777 /tmp/php_sessions \
    && printf "session.save_path=/tmp/php_sessions\n" \
       > /usr/local/etc/php/conf.d/sessions.ini \
    && printf "[opcache]\nopcache.enable=1\nopcache.memory_consumption=128\nopcache.interned_strings_buffer=16\nopcache.max_accelerated_files=10000\nopcache.fast_shutdown=1\n" \
       > /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html
COPY . .

# Download Bootstrap assets at build time (same as before)
RUN mkdir -p assets/css/fonts assets/js \
    && curl -sL "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" \
            -o assets/css/bootstrap.min.css \
    && curl -sL "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" \
            -o assets/js/bootstrap.bundle.min.js \
    && curl -sL "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" \
            -o assets/css/bootstrap-icons.css \
    && curl -sL "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/fonts/bootstrap-icons.woff2" \
            -o assets/css/fonts/bootstrap-icons.woff2 \
    && curl -sL "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/fonts/bootstrap-icons.woff" \
            -o assets/css/fonts/bootstrap-icons.woff

# Allow .htaccess overrides (needed for uploads PHP execution block)
RUN printf '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' >> /etc/apache2/apache2.conf

# Startup script: set Apache to listen on Render's dynamic $PORT
RUN printf '#!/bin/sh\n\
PORT=${PORT:-8080}\n\
sed -i "s/Listen 80$/Listen $PORT/" /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:$PORT>/" /etc/apache2/sites-enabled/000-default.conf\n\
exec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

RUN rm -rf database/ docs/

CMD ["/usr/local/bin/start.sh"]
