FROM php:8.5-apache-bookworm AS dependencies

WORKDIR /var/www/html

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libicu-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" bcmath intl mbstring pdo_mysql zip \
    && a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && rm -rf /var/lib/apt/lists/*

# Sem display_errors=Off, o aviso de corpo acima do post_max_size sai antes do Laravel e impede a resposta 413.
RUN printf 'upload_max_filesize=12M\npost_max_size=110M\ndisplay_errors=Off\ndisplay_startup_errors=Off\nlog_errors=On\n' \
    > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY . .

RUN COMPOSER_ALLOW_SUPERUSER=1 composer install \
    --no-interaction \
    --prefer-dist \
    --no-progress \
    --optimize-autoloader

FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts

COPY --from=dependencies /var/www/html/resources ./resources
COPY --from=dependencies /var/www/html/public ./public
COPY --from=dependencies /var/www/html/vendor ./vendor
COPY vite.config.js ./

FROM frontend AS assets

RUN npm run build

FROM dependencies AS app

COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/app/public storage/app/private \
        storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

EXPOSE 80

CMD ["apache2-foreground"]
