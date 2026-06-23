<?php

/**
 * -------------------------------------------------------------------------
 * googlessoauth plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2026 by the googlessoauth plugin team.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/googlessoauth
 * -------------------------------------------------------------------------
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Googlessoauth\Hook;

/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define('PLUGIN_GOOGLESSOAUTH_NAME', 'Google SSO Authentication');
define('PLUGIN_GOOGLESSOAUTH_VERSION', '0.2.0');
define('PLUGIN_GOOGLESSOAUTH_AUTHOR', 'Lucas');
define('PLUGIN_GOOGLESSOAUTH_LICENSE', 'GPL-3.0');
define('PLUGIN_GOOGLESSOAUTH_HOMEPAGE', 'https://github.com/lucasvmigotto/googlessoauth');

// Load the plugin's own Composer dependencies (league/oauth2-client, ...).
// GLPI registers a PSR-4 autoloader for the plugin `src/` directory but does
// not load the plugin's `vendor/autoload.php`, so we do it here.
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Minimal GLPI version, inclusive
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_GOOGLESSOAUTH_MIN_GLPI_VERSION", "11.0.0");

// Maximum GLPI version, exclusive
/** @phpstan-ignore theCodingMachineSafe.function (safe to assume this isn't already defined) */
define("PLUGIN_GOOGLESSOAUTH_MAX_GLPI_VERSION", "11.0.99");

/**
 * Init hooks of the plugin.
 * REQUIRED
 */
function plugin_init_googlessoauth(): void
{
    /** @var array<string, mixed> $PLUGIN_HOOKS */
    global $PLUGIN_HOOKS;

    // Required so GLPI accepts POST requests to our front controllers.
    $PLUGIN_HOOKS['csrf_compliant']['googlessoauth'] = true;

    // -----------------------------------------------------------------
    // Public (unauthenticated) OAuth endpoints.
    //
    // GLPI 11 protects plugin legacy scripts with STRATEGY_AUTHENTICATED by
    // default. The OAuth start (`redirect.php`) and the Google callback
    // (`callback.php`) are reached *before* the user is logged in, so without
    // this they bounce back to the login page with "session expired" (error=3).
    // STRATEGY_NO_CHECK still starts the PHP session (needed to store/validate
    // the OAuth state) but does not require an authenticated user.
    // -----------------------------------------------------------------
    if (method_exists(\Glpi\Http\Firewall::class, 'addPluginStrategyForLegacyScripts')) {
        \Glpi\Http\Firewall::addPluginStrategyForLegacyScripts(
            'googlessoauth',
            '#^/front/(redirect|callback)\.php#',
            \Glpi\Http\Firewall::STRATEGY_NO_CHECK
        );
    }

    // -----------------------------------------------------------------
    // Anonymous-page assets.
    //
    // The login page is rendered for *unauthenticated* visitors, so the
    // regular `add_css` / `add_javascript` hooks (which only fire on
    // authenticated pages) would never run. We must use the *_ANONYMOUS_PAGE
    // variants instead.
    // -----------------------------------------------------------------

    // Compiled stylesheet that hides the standard login form (see login.scss).
    $PLUGIN_HOOKS[Hooks::ADD_CSS_ANONYMOUS_PAGE]['googlessoauth'] = 'public/dist/login.css';

    // Synchronous "gate" script. Rendered as a blocking <script src> in <head>,
    // it runs *before* the body is painted and reveals the standard form when
    // the break-glass `?hilfe=1` parameter is present (emergency local login).
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT_ANONYMOUS_PAGE]['googlessoauth'] = 'public/dist/gate.js';

    // Progressive-enhancement module (button loading state, etc.).
    $PLUGIN_HOOKS[Hooks::ADD_JAVASCRIPT_MODULE_ANONYMOUS_PAGE]['googlessoauth'] = 'public/dist/login.js';

    // Inject the "Login with Google" button into the login view.
    $PLUGIN_HOOKS[Hooks::DISPLAY_LOGIN]['googlessoauth'] = [Hook::class, 'displayLogin'];

    // Backend configuration screen (Client ID / Secret / allowed domains).
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['googlessoauth'] = 'front/config.form.php';
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array{
 *      name: string,
 *      version: string,
 *      author: string,
 *      license: string,
 *      homepage: string,
 *      requirements: array{
 *          glpi: array{
 *              min: string,
 *              max: string,
 *          }
 *      }
 * }
 */
function plugin_version_googlessoauth(): array
{
    return [
        'name'           => PLUGIN_GOOGLESSOAUTH_NAME,
        'version'        => PLUGIN_GOOGLESSOAUTH_VERSION,
        'author'         => PLUGIN_GOOGLESSOAUTH_AUTHOR,
        'license'        => PLUGIN_GOOGLESSOAUTH_LICENSE,
        'homepage'       => PLUGIN_GOOGLESSOAUTH_HOMEPAGE,
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_GOOGLESSOAUTH_MIN_GLPI_VERSION,
                'max' => PLUGIN_GOOGLESSOAUTH_MAX_GLPI_VERSION,
            ],
        ],
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONAL
 */
function plugin_googlessoauth_check_prerequisites(): bool
{
    return true;
}

/**
 * Check configuration process
 * OPTIONAL
 *
 * @param bool $verbose Whether to display message on failure. Defaults to false.
 */
function plugin_googlessoauth_check_config(bool $verbose = false): bool
{
    if (\GlpiPlugin\Googlessoauth\Config::isConfigured()) {
        return true;
    }

    if ($verbose) {
        echo __('Google OAuth Client ID and Secret are not configured.', 'googlessoauth');
    }

    // Returning false would prevent the plugin from being usable. We still
    // return true so the plugin can be activated and configured afterwards.
    return true;
}
