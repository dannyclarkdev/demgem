# syntax=docker/dockerfile:1
#
# demgem, in one runtime container.
#
# FrankenPHP serves HTTP and runs PHP in the same process, so this stack has no nginx
# service, no php-fpm socket, and no fastcgi_pass for a self-hoster to get wrong at
# midnight. Herd stays the development path; this is for running the thing.
#
# Tags are pinned on purpose. An image that floats is an image that breaks on a
# Tuesday for a reason nobody can reproduce.

# 1. The PHP platform, shared by the dependency stage and the runtime.
#
#    The dependencies are installed on the image that runs them, not on the composer
#    image: spatie/image requires ext-exif, and a lock file resolved on a platform
#    that lacks it is a lie that only shows up in production.
FROM dunglas/frankenphp:1.12.7-php8.4-alpine AS base

RUN install-php-extensions pdo_pgsql gd exif intl zip opcache pcntl redis \
    && apk add --no-cache postgresql-client

# 2. PHP dependencies, production only.
FROM base AS vendor
COPY --from=composer:2.10 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative

# 3. The front end. Tailwind scans the Blade templates, so this stage needs the app,
#    not just resources/.
FROM node:22.23-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY . .
RUN npm run build

# 4. The runtime.
FROM base AS app

WORKDIR /app

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/opcache.ini" \
    && { \
        echo 'upload_max_filesize=8M'; \
        echo 'post_max_size=10M'; \
    } > "$PHP_INI_DIR/conf.d/demgem.ini"

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
COPY docker/entrypoint.sh /usr/local/bin/demgem-entrypoint

# The named volume that compose mounts at /app/storage inherits this ownership the
# first time it is created, which is why the chown happens here and not at boot.
RUN chmod +x /usr/local/bin/demgem-entrypoint \
    && mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    # Caddy writes its certificate authority and its autosave config under these two,
    # and the image ships them owned by root. Without this the server refuses to
    # start as www-data with "mkdir /data/caddy/pki: permission denied".
    && mkdir -p /data/caddy /config/caddy \
    && chown -R www-data:www-data /data /config

ENV SERVER_NAME=":8000" \
    APP_ENV=production \
    APP_DEBUG=false \
    AUTO_MIGRATE=true

EXPOSE 8000

USER www-data

ENTRYPOINT ["demgem-entrypoint"]

# The base image's own command. Its entrypoint turns a leading "--" into
# "frankenphp run --", and the shipped Caddyfile serves {$SERVER_ROOT:public/}
# through php_server, which is the routing Laravel wants.
CMD ["--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
