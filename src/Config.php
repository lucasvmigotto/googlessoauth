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

namespace GlpiPlugin\Googlessoauth;

use Config as GlpiConfig;
use GLPIKey;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Session;

/**
 * Configuration storage and accessors for the Google SSO plugin.
 *
 * Values are primarily read from the GLPI configuration table (context
 * `plugin:googlessoauth`), with a fallback to environment variables. The
 * client secret is stored encrypted at rest using GLPI's instance key.
 */
final class Config
{
    /** GLPI configuration context for this plugin. */
    public const CONTEXT = 'plugin:googlessoauth';

    /** @var array<string, string> Default values for known config keys. */
    private const DEFAULTS = [
        'client_id'        => '',
        'client_secret'    => '',
        'allowed_domains'  => '',
    ];

    /**
     * Create the configuration entries on plugin install.
     */
    public static function install(): void
    {
        $existing = GlpiConfig::getConfigurationValues(self::CONTEXT);
        $to_add = [];
        foreach (self::DEFAULTS as $key => $value) {
            if (!array_key_exists($key, $existing)) {
                $to_add[$key] = $value;
            }
        }
        if ($to_add !== []) {
            GlpiConfig::setConfigurationValues(self::CONTEXT, $to_add);
        }
    }

    /**
     * Drop the configuration entries on plugin uninstall.
     */
    public static function uninstall(): void
    {
        GlpiConfig::deleteConfigurationValues(self::CONTEXT, array_keys(self::DEFAULTS));
    }

    /**
     * Get the Google OAuth Client ID (DB first, env fallback).
     */
    public static function getClientId(): string
    {
        $value = self::getRawValue('client_id');
        if ($value !== '') {
            return $value;
        }
        return trim((string) getenv('GOOGLE_CLIENT_ID'));
    }

    /**
     * Get the Google OAuth Client Secret (DB first, env fallback).
     *
     * The DB value is stored encrypted and is transparently decrypted here.
     */
    public static function getClientSecret(): string
    {
        $stored = self::getRawValue('client_secret');
        if ($stored !== '') {
            $decrypted = (new GLPIKey())->decrypt($stored);
            if ($decrypted !== null && $decrypted !== '') {
                return $decrypted;
            }
        }
        return trim((string) getenv('GOOGLE_CLIENT_SECRET'));
    }

    /**
     * Get the list of allowed Google Workspace domains.
     *
     * An empty list means "no domain restriction". DB value (comma separated)
     * takes precedence over the `GOOGLE_ALLOWED_DOMAINS` environment variable.
     *
     * @return list<string> Lower-cased domains.
     */
    public static function getAllowedDomains(): array
    {
        $raw = self::getRawValue('allowed_domains');
        if ($raw === '') {
            $raw = (string) getenv('GOOGLE_ALLOWED_DOMAINS');
        }

        $domains = [];
        foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $domain) {
            $domain = strtolower(trim($domain));
            if ($domain !== '') {
                $domains[] = $domain;
            }
        }
        return array_values(array_unique($domains));
    }

    /**
     * Whether the minimum configuration (client id + secret) is present.
     */
    public static function isConfigured(): bool
    {
        return self::getClientId() !== '' && self::getClientSecret() !== '';
    }

    /**
     * Persist the configuration submitted from the admin form.
     *
     * @param array<string, mixed> $input Raw form input.
     */
    public static function updateFromForm(array $input): void
    {
        $values = [];

        if (array_key_exists('client_id', $input)) {
            $values['client_id'] = trim((string) $input['client_id']);
        }

        if (array_key_exists('allowed_domains', $input)) {
            $values['allowed_domains'] = trim((string) $input['allowed_domains']);
        }

        // Only update the secret when a non-empty value is submitted, so that
        // re-saving the form without retyping the secret keeps the stored one.
        if (isset($input['client_secret']) && trim((string) $input['client_secret']) !== '') {
            $values['client_secret'] = (new GLPIKey())->encrypt(trim((string) $input['client_secret']));
        }

        if ($values !== []) {
            GlpiConfig::setConfigurationValues(self::CONTEXT, $values);
        }
    }

    /**
     * Render the configuration form (used by front/config.form.php).
     */
    public static function showConfigForm(): void
    {
        $allowed_domains = self::getRawValue('allowed_domains');
        if ($allowed_domains === '') {
            $allowed_domains = (string) getenv('GOOGLE_ALLOWED_DOMAINS');
        }

        TemplateRenderer::getInstance()->display('@googlessoauth/config.html.twig', [
            'client_id'        => self::getRawValue('client_id') !== '' ? self::getRawValue('client_id') : (string) getenv('GOOGLE_CLIENT_ID'),
            'has_secret'       => self::getClientSecret() !== '',
            'allowed_domains'  => $allowed_domains,
            'redirect_uri'     => self::getRedirectUri(),
            'from_env'         => [
                'client_id'       => self::getRawValue('client_id') === '' && getenv('GOOGLE_CLIENT_ID') !== false,
                'client_secret'   => self::getRawValue('client_secret') === '' && getenv('GOOGLE_CLIENT_SECRET') !== false,
            ],
            'form_action'      => Html::getPrefixedUrl('/plugins/googlessoauth/front/config.form.php'),
            'csrf_token'       => Session::getNewCSRFToken(),
        ]);
    }

    /**
     * The OAuth2 redirect (callback) URI registered with Google.
     *
     * Must be an ABSOLUTE url (scheme + host), because Google rejects relative
     * redirect URIs and matches it exactly against the registered one. We build
     * it from GLPI's configured base url (`$CFG_GLPI['url_base']`, which already
     * includes any root_doc sub-path) rather than `Html::getPrefixedUrl()`,
     * which only returns a host-relative path.
     */
    public static function getRedirectUri(): string
    {
        /** @var array<string, mixed> $CFG_GLPI */
        global $CFG_GLPI;

        $base = rtrim((string) ($CFG_GLPI['url_base'] ?? ''), '/');
        return $base . '/plugins/googlessoauth/front/callback.php';
    }

    /**
     * Read a raw stored value from the GLPI config table.
     */
    private static function getRawValue(string $name): string
    {
        $values = GlpiConfig::getConfigurationValues(self::CONTEXT, [$name]);
        return isset($values[$name]) ? trim((string) $values[$name]) : '';
    }
}
