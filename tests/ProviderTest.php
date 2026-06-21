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

namespace GlpiPlugin\Googlessoauth\Tests;

use GlpiPlugin\Googlessoauth\Provider;
use PHPUnit\Framework\TestCase;

final class ProviderTest extends TestCase
{
    /**
     * @return array<string, array{string, list<string>, bool}>
     */
    public static function domainProvider(): array
    {
        return [
            'no restriction allows any'      => ['user@anything.com', [], true],
            'matching domain'                => ['user@example.com', ['example.com'], true],
            'matching among several'         => ['user@example.org', ['example.com', 'example.org'], true],
            'case-insensitive domain'        => ['User@Example.COM', ['example.com'], true],
            'non-matching domain'            => ['user@evil.com', ['example.com'], false],
            'missing at-sign is rejected'    => ['not-an-email', ['example.com'], false],
            'subdomain is not a match'       => ['user@mail.example.com', ['example.com'], false],
        ];
    }

    /**
     * @param list<string> $allowed
     *
     * @dataProvider domainProvider
     */
    public function testIsDomainAllowed(string $email, array $allowed, bool $expected): void
    {
        $this->assertSame($expected, Provider::isDomainAllowed($email, $allowed));
    }
}
