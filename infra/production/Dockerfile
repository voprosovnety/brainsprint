FROM composer:2.8.12@sha256:5248900ab8b5f7f880c2d62180e40960cd87f60149ec9a1abfd62ac72a02577c AS composer
FROM php:8.5-apache-bookworm@sha256:0c06f9fbf73ee418224a6abb7ebaf7ea9bec5cda11838687798022b51b6ecc3b
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev unzip \
    && docker-php-ext-install -j2 pdo_pgsql intl bcmath zip \
    && a2enmod rewrite headers && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY apps/api/ ./
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
COPY infra/production/apache.conf /etc/apache2/sites-available/000-default.conf
COPY infra/production/apache-limits.conf /etc/apache2/conf-enabled/brainsprint-limits.conf
COPY infra/production/php.ini /usr/local/etc/php/conf.d/brainsprint.ini
COPY infra/production/start.sh /usr/local/bin/brainsprint-start
RUN chmod 755 /usr/local/bin/brainsprint-start && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf
ENV PORT=8080 APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
EXPOSE 8080
STOPSIGNAL SIGWINCH
HEALTHCHECK --interval=30s --timeout=5s --start-period=15s CMD curl --fail --silent http://127.0.0.1:${PORT}/up -H "Host: healthcheck.railway.app" > /dev/null || exit 1
ENTRYPOINT ["brainsprint-start"]
CMD ["apache2-foreground"]
