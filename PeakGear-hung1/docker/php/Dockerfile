FROM php:8.2-fpm-bookworm

ARG HOST_UID=1000
ARG HOST_GID=1000

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    libxml2-dev \
    libxslt-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    supervisor \
    cron \
    git \
    curl \
    unzip \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean

RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    zip \
    intl \
    bcmath \
    gd \
    xsl \
    soap \
    mbstring \
    opcache \
    sockets

RUN pecl install redis-6.0.2 \
    && docker-php-ext-enable redis

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer --version=2.7.0

RUN id -u www-data &>/dev/null || useradd -m -u 1000 -s /bin/bash www-data

RUN mkdir -p /var/www/html && chown -R www-data:www-data /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.conf

RUN mkdir -p /var/log/supervisor

COPY docker/php/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
