FROM php:8.3-apache

ARG HUMHUB_VERSION=1.18.4

RUN apt-get update && apt-get install -y --no-install-recommends \
    cron curl default-mysql-client imagemagick libfreetype6-dev libicu-dev \
    libjpeg62-turbo-dev libmagickwand-dev libpng-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" exif gd intl mysqli opcache pdo_mysql zip \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && a2enmod rewrite headers expires remoteip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -fsSL "https://download.humhub.com/downloads/install/humhub-${HUMHUB_VERSION}.tar.gz" \
      -o /tmp/humhub.tar.gz \
    && tar -xzf /tmp/humhub.tar.gz --strip-components=1 -C /var/www/html \
    && rm /tmp/humhub.tar.gz \
    && cp /var/www/html/.htaccess.dist /var/www/html/.htaccess \
    && chown -R www-data:www-data /var/www/html

COPY ops/apache.conf /etc/apache2/conf-available/human-agent-social.conf
RUN a2enconf human-agent-social

COPY ops/entrypoint.sh /usr/local/bin/human-agent-entrypoint
RUN chmod 0755 /usr/local/bin/human-agent-entrypoint

WORKDIR /var/www/html
ENTRYPOINT ["human-agent-entrypoint"]
CMD ["apache2-foreground"]

