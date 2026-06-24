# googlessoauth — Google SSO Authentication for GLPI 11

Replaces the GLPI 11 login form with a single **"Login with Google"** button.
Authentication succeeds **only** when the verified Google email matches an
existing, active GLPI user. Users are **never** auto-created.

## Features

- "Login with Google" button injected on the login page via the anonymous-page
  hooks (`display_login`, `add_css_anonymous_page`, `add_javascript_anonymous_page`,
  `add_javascript_module_anonymous_page`).
- Standard username/password form is hidden by CSS.
- **Break-glass**: append `?hilfe=1` to the login URL to reveal the standard
  local-login form for emergency admin access. A tiny head-blocking script
  toggles this before paint, so there is no flash of the hidden form.
- **No automatic redirects** — the OAuth flow starts only on an explicit click.
- Strict, case-insensitive email matching against `glpi_useremails` then the
  user login name; only active, non-deleted accounts are accepted.
- Optional allowed-domain restriction.
- Client secret stored **encrypted** (GLPI instance key), with environment
  variable fallback.

## Configuration

1. In Google Cloud, create an **OAuth 2.0 Client (Web application)** and register
   the redirect URI shown on the plugin configuration screen, i.e.
   `https://<your-glpi>/plugins/googlessoauth/front/callback.php`.
2. In GLPI: **Setup → Plugins → Google SSO Authentication** (the cog/config
   page) and enter the **Client ID**, **Client Secret** and optional
   **Allowed domains**.
3. Mount, or write during Dockerfile build, the `<GLPI_ROOT>/config/local_define.php`
   as the `local_define.php` example file. The `SameSite` cookie policy must
   be defined as `Lax`.

### Environment-variable fallback

If the config screen fields are empty, the plugin reads:

| Variable                 | Purpose                                    |
| ------------------------ | ------------------------------------------ |
| `GOOGLE_CLIENT_ID`       | OAuth client id                            |
| `GOOGLE_CLIENT_SECRET`   | OAuth client secret                        |
| `GOOGLE_ALLOWED_DOMAINS` | Comma-separated allowed domains (optional) |

## Build

Frontend assets (TypeScript + SASS) compile to `public/dist/` with Bun:

```bash
bun install
bun run build      # or: bun run watch
```

Backend dependencies (league/oauth2-google) install with Composer:

```bash
composer install --no-dev --optimize-autoloader
```

## Docker deployment

The provided `Dockerfile` builds the assets and vendor dir in dedicated stages
and ships them into the GLPI image. To inject into an existing GLPI Dockerfile:

```dockerfile
COPY --chown=www-data:www-data \
    . /var/www/glpi/plugins/googlessoauth/
```

(ensure `public/dist/` and `vendor/` are built/copied as in the bundled
`Dockerfile`). A `.dockerignore` keeps `.env`, `vendor/`, `node_modules/` and
dev tooling out of the image.

## Development

A `.devcontainer/` is provided: it provisions a full GLPI 11 tree, mounts this
plugin at `plugins/googlessoauth`, starts MySQL, installs all dependencies and
the database, then activates the plugin. Open the folder in a Dev Container and
run from `/var/www/glpi`:

```bash
php bin/console serve --address=0.0.0.0 --port=8088
```

## Contributing

- Open a ticket for each bug/feature so it can be discussed
- Follow [development guidelines](http://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html)
- Work on a new branch on your own fork and open a PR
