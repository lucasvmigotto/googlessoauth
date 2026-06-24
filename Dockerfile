# syntax=docker/dockerfile:1

ARG GLPI_VERSION=11
ARG BUN_VERSION="1-debian13-dev"
ARG COMPOSER_VERSION="2-debian13-php8.4-dev"

FROM dhi.io/bun:${BUN_VERSION} AS frontend

WORKDIR /src

COPY ./public /src/public/

RUN --mount=type=bind,source=package.json,target=package.json \
    --mount=type=bind,source=bun.lock,target=bun.lock \
    --mount=type=bind,source=tsconfig.json,target=tsconfig.json \
    --mount=type=bind,source=scripts,target=scripts \
    bun install --frozen-lockfile \
    && bun run build

FROM dhi.io/composer:${COMPOSER_VERSION} AS backend

ARG COOKIE_SECURE="1"
ARG COOKIE_HTTPONLY="1"
ARG COOKIE_SAMESITE="Lax"

WORKDIR /app

RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader \
    && cat <<EOF > local_define.php
<?php
    ini_set('session.cookie_secure', ${COOKIE_SECURE});
    ini_set('session.cookie_httponly', ${COOKIE_HTTPONLY});
    ini_set('session.cookie_samesite', '${COOKIE_SAMESITE}');
    if (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        \$_SERVER['HTTPS'] = 'on';
        \$_SERVER['SERVER_PORT'] = 443;
    }
EOF

FROM glpi/glpi:${GLPI_VERSION}

COPY --chown=www-data:www-data \
    . /var/www/glpi/plugins/googlessoauth/

COPY --from=backend \
    --chown=www-data:www-data \
    /app/vendor/ /var/www/glpi/plugins/googlessoauth/vendor/

COPY --from=backend \
    --chown=www-data:www-data \
    /app/local_define.php /var/www/glpi/config/local_define.php

COPY --from=frontend \
    --chown=www-data:www-data \
    /src/public/dist/ /var/www/glpi/plugins/googlessoauth/public/dist/
