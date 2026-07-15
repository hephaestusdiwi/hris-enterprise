FROM php:8.3-fpm-alpine

# ---------------------------------------------------------------------------
# Build dependencies — dihapus lagi di akhir supaya image final tetap kecil
# (target deploy VPS Natanetwork single-core/RAM terbatas, lihat docs/DOCKER.md).
# Runtime shared library yang beneran dibutuhkan dideteksi otomatis pakai
# scanelf (teknik yang sama dipakai image resmi docker-library/php sendiri),
# bukan nebak nama paket -libs manual — nama paket runtime di Alpine suka
# berubah antar rilis (misal postgresql-libs pernah berganti jadi libpq).
# ---------------------------------------------------------------------------
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        pax-utils \
        postgresql-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        icu-dev \
        linux-headers \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        gd \
        zip \
        bcmath \
        exif \
        intl \
        pcntl \
    # opcache sudah ke-compile bawaan image resmi php-alpine, tinggal di-enable.
    && docker-php-ext-enable opcache \
    # Redis belum ada extension resmi bawaan PHP, jadi lewat PECL.
    && pecl install redis \
    && docker-php-ext-enable redis \
    && runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' \
            | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )" \
    && apk add --no-cache --virtual .phpext-rundeps $runDeps \
    && apk del .build-deps \
    && rm -rf /tmp/pear

# php.ini custom (upload size, timezone, opcache tuning, dll)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/99-hris.ini

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
