# syntax=docker/dockerfile:1

ARG CURL_VERSION="8-debian13-dev"
ARG GLPI_VERSION=11
ARG BUN_VERSION="1-debian13-dev"
ARG COMPOSER_VERSION="2-debian13-php8.4-dev"

FROM dhi.io/curl:${CURL_VERSION} AS curl

ARG ADVANCEDFORMS_VERSION="1.1.1"
ARG FIELDS_VERSION="1.24.0"
ARG GANTT_VERSION="1.3.3"
ARG REPORTS_VERSION="2.0.4"

WORKDIR /app

RUN curl -fsSLo "/tmp/glpi-advancedforms-${ADVANCEDFORMS_VERSION}.tar.bz2" \
    "https://github.com/pluginsGLPI/advancedforms/releases/download/${ADVANCEDFORMS_VERSION}/glpi-advancedforms-${ADVANCEDFORMS_VERSION}.tar.bz2" \
    && tar -xjvf "/tmp/glpi-advancedforms-${ADVANCEDFORMS_VERSION}.tar.bz2" \
    && curl -fsSLo "/tmp/glpi-fields-${FIELDS_VERSION}.tar.bz2" \
    "https://github.com/pluginsGLPI/fields/releases/download/${FIELDS_VERSION}/glpi-fields-${FIELDS_VERSION}.tar.bz2" \
    && tar -xjvf "/tmp/glpi-fields-${FIELDS_VERSION}.tar.bz2" \
    && curl -fsSLo "/tmp/glpi-gantt-${GANTT_VERSION}.tar.bz2" \
    "https://github.com/pluginsGLPI/gantt/releases/download/${GANTT_VERSION}/glpi-gantt-${GANTT_VERSION}.tar.bz2" \
    && tar -xjvf "/tmp/glpi-gantt-${GANTT_VERSION}.tar.bz2" \
    && curl -fsSLo "/tmp/glpi-reports-${REPORTS_VERSION}.tar.bz2" \
    "https://github.com/InfotelGLPI/reports/releases/download/${REPORTS_VERSION}/glpi-reports-${REPORTS_VERSION}.tar.bz2" \
    && tar -xjvf "/tmp/glpi-reports-${REPORTS_VERSION}.tar.bz2"

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

WORKDIR /app

RUN --mount=type=bind,source=composer.json,target=composer.json \
    --mount=type=bind,source=composer.lock,target=composer.lock \
    composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --optimize-autoloader

FROM glpi/glpi:${GLPI_VERSION}

COPY --from=curl \
    --chown=www-data:www-data \
    /app/ /var/www/glpi/plugins/

COPY --chown=www-data:www-data \
    . /var/www/glpi/plugins/googlessoauth/

COPY --from=backend \
    --chown=www-data:www-data \
    /app/vendor/ /var/www/glpi/plugins/googlessoauth/vendor/

COPY --from=frontend \
    --chown=www-data:www-data \
    /src/public/dist/ /var/www/glpi/plugins/googlessoauth/public/dist/
