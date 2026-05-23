FROM php:8.3-apache-bookworm

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/ldap/ad-ca.crt /tmp/rockdesk-ad-ca.crt

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libcurl4-openssl-dev \
        libfreetype6-dev \
        libicu-dev \
        libc-client-dev \
        libjpeg62-turbo-dev \
        libldap2-dev \
        libonig-dev \
        libpng-dev \
        libkrb5-dev \
        libsqlite3-dev \
        libzip-dev \
        openssl \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j"$(nproc)" \
        exif \
        gd \
        imap \
        intl \
        ldap \
        mbstring \
        mysqli \
        pdo_mysql \
        pdo_sqlite \
    && if ! getent group 1000 >/dev/null; then groupadd --gid 1000 hostdev; fi \
    && if ! getent passwd 1000 >/dev/null; then useradd --uid 1000 --gid 1000 --no-create-home --shell /usr/sbin/nologin hostdev; fi \
    && a2enmod rewrite \
    && printf "ServerName localhost\n" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && sed -ri 's/^export APACHE_RUN_USER=.*/export APACHE_RUN_USER=${APACHE_RUN_USER:-www-data}/' /etc/apache2/envvars \
    && sed -ri 's/^export APACHE_RUN_GROUP=.*/export APACHE_RUN_GROUP=${APACHE_RUN_GROUP:-www-data}/' /etc/apache2/envvars \
    && mkdir -p /etc/ldap/certs \
    && cp /tmp/rockdesk-ad-ca.crt /etc/ldap/certs/ad-ca.crt \
    && if openssl x509 -in /tmp/rockdesk-ad-ca.crt -noout >/dev/null 2>&1; then \
        cp /tmp/rockdesk-ad-ca.crt /usr/local/share/ca-certificates/rockdesk-ad-ca.crt \
        && update-ca-certificates; \
    else \
        printf "Skipping AD CA trust install; docker/ldap/ad-ca.crt is a placeholder.\n"; \
    fi \
    && printf "TLS_CACERT /etc/ssl/certs/ca-certificates.crt\n" >> /etc/ldap/ldap.conf \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader

WORKDIR /var/www/html
