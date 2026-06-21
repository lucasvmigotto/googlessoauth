#!/usr/bin/env bash
#
# Provisions a full GLPI 11 development tree around the mounted plugin so that
# the `../../` tooling references (phpstan, eslint, stylelint, Makefile) resolve
# and the plugin can be exercised end-to-end.
#
set -euo pipefail

GLPI_VERSION="${GLPI_VERSION:-11.0.x}"
GLPI_ROOT="/var/www/glpi"
PLUGIN_DIR="${GLPI_ROOT}/plugins/googlessoauth"

echo "==> Provisioning GLPI ${GLPI_VERSION} into ${GLPI_ROOT}"

if [ ! -f "${GLPI_ROOT}/inc/includes.php" ] && [ ! -f "${GLPI_ROOT}/composer.json" ]; then
    tmp="$(mktemp -d)"
    git clone --depth 1 --branch "${GLPI_VERSION}" https://github.com/glpi-project/glpi.git "${tmp}/glpi"
    # Copy GLPI source around the already-mounted plugin (never overwrite it).
    rsync -a --exclude 'plugins/googlessoauth' "${tmp}/glpi/" "${GLPI_ROOT}/"
    rm -rf "${tmp}"
else
    echo "    GLPI source already present, skipping clone."
fi

echo "==> Installing GLPI core dependencies"
cd "${GLPI_ROOT}"
composer install --no-interaction --prefer-dist || true
if [ -f package.json ]; then
    npm install --no-audit --no-fund || true
    npm run build || true
fi

echo "==> Installing plugin backend dependencies"
cd "${PLUGIN_DIR}"
composer install --no-interaction --prefer-dist

echo "==> Installing & building plugin frontend assets"
bun install
bun run build

echo "==> Waiting for database to be ready"
for _ in $(seq 1 30); do
    if mysqladmin ping -h"${GLPI_DB_HOST:-db}" -u"${GLPI_DB_USER:-glpi}" -p"${GLPI_DB_PASSWORD:-glpi}" --silent 2>/dev/null; then
        break
    fi
    sleep 2
done

echo "==> Installing GLPI database (idempotent)"
cd "${GLPI_ROOT}"
if [ -x bin/console ]; then
    php bin/console database:install \
        --db-host="${GLPI_DB_HOST:-db}" \
        --db-port="${GLPI_DB_PORT:-3306}" \
        --db-name="${GLPI_DB_NAME:-glpi}" \
        --db-user="${GLPI_DB_USER:-glpi}" \
        --db-password="${GLPI_DB_PASSWORD:-glpi}" \
        --no-interaction --force --reconfigure || \
        echo "    Database already installed or install skipped."

    echo "==> Activating googlessoauth plugin"
    php bin/console plugin:install --username=glpi googlessoauth || true
    php bin/console plugin:activate googlessoauth || true
fi

cat <<'EOF'

============================================================
 googlessoauth dev environment ready.

 Start GLPI dev server (from /var/www/glpi):
   php bin/console serve --address=0.0.0.0 --port=8088

 Then open http://localhost:8088
 Default admin: glpi / glpi
============================================================
EOF
