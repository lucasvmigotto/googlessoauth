# Development image for the googlessoauth GLPI 11 plugin.
# Provides PHP 8.3 with all GLPI-required extensions, Composer, Node, Bun
# and the MySQL client. The actual GLPI source tree is provisioned by
# post-create.sh so that the plugin lives at plugins/googlessoauth and the
# `../../` tooling references (phpstan, eslint, stylelint, Makefile) resolve.
FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm

# GLPI 11 required / recommended PHP extensions.
# `install-php-extensions` ships with the devcontainers PHP image.
RUN install-php-extensions \
        mysqli \
        gd \
        intl \
        zip \
        bz2 \
        curl \
        mbstring \
        exif \
        ldap \
        opcache \
        sodium \
        apcu

# MySQL client + misc tooling.
RUN apt-get update \
    && export DEBIAN_FRONTEND=noninteractive \
    && apt-get install -y --no-install-recommends \
        default-mysql-client \
        rsync \
        unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Bun (used to build the frontend assets).
RUN su vscode -c "curl -fsSL https://bun.sh/install | bash" \
    && ln -sf /home/vscode/.bun/bin/bun /usr/local/bin/bun
