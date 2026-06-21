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

use Auth;
use DBmysql;
use Glpi\DBAL\QueryExpression;
use Glpi\Error\ErrorHandler;
use Glpi\Exception\AuthenticationFailedException;
use Glpi\Exception\Http\BadRequestHttpException;
use Html;
use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Session;
use Toolbox;
use User;

/**
 * Encapsulates the Google OAuth2 flow and the strict user-matching logic.
 *
 * Authorization succeeds ONLY when the verified Google email maps to an
 * existing, active, non-deleted GLPI user. Users are never auto-created.
 */
final class Provider
{
    /** Session key holding the CSRF/state value of an in-flight OAuth request. */
    private const SESSION_STATE = 'plugin_googlessoauth_state';

    /** Session key holding the post-login redirect target. */
    private const SESSION_REDIRECT = 'plugin_googlessoauth_redirect';

    /**
     * Build the league Google provider from the stored configuration.
     */
    private static function buildProvider(): Google
    {
        return new Google([
            'clientId'     => Config::getClientId(),
            'clientSecret' => Config::getClientSecret(),
            'redirectUri'  => Config::getRedirectUri(),
        ]);
    }

    /**
     * Start the OAuth flow: generate the Google authorization URL and persist
     * the state token in the GLPI session.
     *
     * @param string $redirect Optional GLPI redirect target for after login.
     */
    public static function startAuthorization(string $redirect = ''): string
    {
        if (!Config::isConfigured()) {
            throw new BadRequestHttpException('Google SSO is not configured.');
        }

        $provider = self::buildProvider();

        $options = ['scope' => ['openid', 'email', 'profile']];
        $domains = Config::getAllowedDomains();
        if (count($domains) === 1) {
            // Hint Google to pre-select the single allowed Workspace domain.
            $options['hd'] = $domains[0];
        }

        $url = $provider->getAuthorizationUrl($options);

        // Persist state in the *normal* GLPI session (not a stateless cookie)
        // so the callback can validate it without state-mismatch loops.
        $_SESSION[self::SESSION_STATE]    = $provider->getState();
        $_SESSION[self::SESSION_REDIRECT] = $redirect;

        return $url;
    }

    /**
     * Handle the OAuth callback. On success the GLPI session is established and
     * the browser is redirected. On failure a structured exception is thrown so
     * GLPI renders a clear error on the standard login screen (no bare
     * header() redirects, no auto-retry loops).
     *
     * @param array<string, mixed> $params The request query parameters.
     *
     * @return never
     */
    public static function handleCallback(array $params): void
    {
        // 1. Google reported an error (e.g. user denied consent).
        if (isset($params['error'])) {
            $error = new BadRequestHttpException('OAuth error: ' . (string) $params['error']);
            $error->setMessageToDisplay(__('Google authentication was cancelled or failed.', 'googlessoauth'));
            throw $error;
        }

        // 2. Validate the state token against the value stored in our session.
        $expected_state = $_SESSION[self::SESSION_STATE] ?? null;
        $received_state = isset($params['state']) ? (string) $params['state'] : null;
        unset($_SESSION[self::SESSION_STATE]);

        if (
            $expected_state === null
            || $received_state === null
            || !hash_equals((string) $expected_state, $received_state)
        ) {
            $error = new BadRequestHttpException('Invalid OAuth state.');
            $error->setMessageToDisplay(__('Invalid authentication state. Please try again.', 'googlessoauth'));
            throw $error;
        }

        // 3. An authorization code is required to continue.
        if (!isset($params['code']) || (string) $params['code'] === '') {
            $error = new BadRequestHttpException('Missing OAuth authorization code.');
            $error->setMessageToDisplay(__('Missing authorization code from Google.', 'googlessoauth'));
            throw $error;
        }

        $provider = self::buildProvider();

        // 4. Exchange the code for an access token and load the user profile.
        try {
            /** @var AccessToken $token */
            $token = $provider->getAccessToken('authorization_code', [
                'code' => (string) $params['code'],
            ]);

            /** @var GoogleUser $owner */
            $owner = $provider->getResourceOwner($token);
        } catch (\Throwable $e) {
            ErrorHandler::logCaughtException($e);
            $error = new BadRequestHttpException('Token exchange failed.', $e);
            $error->setMessageToDisplay(__('Could not complete authentication with Google.', 'googlessoauth'));
            throw $error;
        }

        $email = strtolower(trim((string) ($owner->getEmail() ?? '')));

        // 5. Require a verified email address.
        if ($email === '' || $owner->getEmailVerified() !== true) {
            throw new AuthenticationFailedException(
                'Email not verified by Google.',
                0,
                null,
                [__('Your Google email address is not verified.', 'googlessoauth')]
            );
        }

        // 6. Enforce the allowed-domain restriction (if configured).
        if (!self::isDomainAllowed($email, Config::getAllowedDomains())) {
            throw new AuthenticationFailedException(
                'Domain not allowed.',
                0,
                null,
                [__('Your email domain is not allowed to sign in.', 'googlessoauth')]
            );
        }

        // 7. Strict match: the email MUST belong to an active GLPI user.
        $users_id = self::findActiveUserByEmail($email);
        if ($users_id === null) {
            throw new AuthenticationFailedException(
                'No matching active GLPI user for ' . $email,
                0,
                null,
                [
                    sprintf(
                        __('No active GLPI account matches %s. Access denied.', 'googlessoauth'),
                        $email
                    ),
                ]
            );
        }

        // 8. Establish the GLPI session for the matched user.
        if (!self::loginUser($users_id)) {
            throw new AuthenticationFailedException(
                'Session initialization denied for user #' . $users_id,
                0,
                null,
                [__('Your account is not authorized to connect to GLPI.', 'googlessoauth')]
            );
        }

        // 9. Success: redirect to the requested page (or the home page).
        $redirect = (string) ($_SESSION[self::SESSION_REDIRECT] ?? '');
        unset($_SESSION[self::SESSION_REDIRECT]);

        if ($redirect !== '') {
            Toolbox::manageRedirect($redirect);
        }
        Html::redirect(Html::getPrefixedUrl('/'));
    }

    /**
     * Whether the given email is allowed by the configured domain restriction.
     *
     * An empty allow-list means "no restriction" (any domain is allowed). The
     * comparison is case-insensitive.
     *
     * @param list<string> $allowed_domains Lower-cased allowed domains.
     */
    public static function isDomainAllowed(string $email, array $allowed_domains): bool
    {
        if ($allowed_domains === []) {
            return true;
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            return false;
        }

        $domain = strtolower(substr($email, $at + 1));
        return $domain !== '' && in_array($domain, $allowed_domains, true);
    }

    /**
     * Find the id of an active, non-deleted GLPI user matching the given email.
     *
     * Matching is case-insensitive and checks, in order:
     *   1. registered user emails (glpi_useremails)
     *   2. the user login name (glpi_users.name)
     *
     * @return int|null The user id, or null when no match is found.
     */
    public static function findActiveUserByEmail(string $email): ?int
    {
        /** @var DBmysql $DB */
        global $DB;

        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        // 1. Match against registered user emails.
        $iterator = $DB->request([
            'SELECT'     => 'glpi_users.id AS id',
            'FROM'       => 'glpi_useremails',
            'INNER JOIN' => [
                'glpi_users' => [
                    'ON' => [
                        'glpi_useremails' => 'users_id',
                        'glpi_users'      => 'id',
                    ],
                ],
            ],
            'WHERE'      => [
                'glpi_users.is_active'  => 1,
                'glpi_users.is_deleted' => 0,
                new QueryExpression(
                    'LOWER(' . DBmysql::quoteName('glpi_useremails.email') . ') = ' . $DB->quoteValue($email)
                ),
            ],
            'LIMIT'      => 1,
        ]);
        foreach ($iterator as $row) {
            return (int) $row['id'];
        }

        // 2. Match against the login name.
        $iterator = $DB->request([
            'SELECT' => 'id',
            'FROM'   => 'glpi_users',
            'WHERE'  => [
                'is_active'  => 1,
                'is_deleted' => 0,
                new QueryExpression(
                    'LOWER(' . DBmysql::quoteName('name') . ') = ' . $DB->quoteValue($email)
                ),
            ],
            'LIMIT'  => 1,
        ]);
        foreach ($iterator as $row) {
            return (int) $row['id'];
        }

        return null;
    }

    /**
     * Establish a GLPI session for an existing user, treating the login as an
     * external (SSO) authentication.
     *
     * Session::init() enforces the active/non-deleted/date checks again and
     * requires the user to have a valid profile authorization, so an
     * unprovisioned account cannot obtain a usable session.
     */
    private static function loginUser(int $users_id): bool
    {
        $user = new User();
        if (!$user->getFromDB($users_id)) {
            return false;
        }

        $auth = new Auth();
        $auth->user          = $user;
        $auth->user_present  = true;
        $auth->extauth       = 1;
        $auth->auth_succeded = true;

        Session::init($auth);

        if (!$auth->auth_succeded || Session::getLoginUserID() === false) {
            return false;
        }

        // Record the last login timestamp, mirroring the standard login flow.
        $user->update([
            'id'         => $users_id,
            'last_login' => date('Y-m-d H:i:s'),
        ]);

        return true;
    }
}
