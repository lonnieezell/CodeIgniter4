<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace CodeIgniter\Debug\Dump\Casters;

use CodeIgniter\Cookie\Cookie;
use CodeIgniter\Debug\Dump\CasterInterface;
use CodeIgniter\Debug\Dump\Dumper;
use CodeIgniter\Debug\Dump\Node;

/**
 * Custom caster for Cookie objects.
 * Displays cookie name, value, and key attributes in a simple format.
 */
class CookieCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Cookie objects
        if (!$value instanceof Cookie) {
            return null;
        }

        return $this->castCookie($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Cookie object.
     */
    private function castCookie(Cookie $cookie, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === NAME ===
        $children['name'] = $dumper->normalize(
            $cookie->getName(),
            "$path.name",
            $depth + 1,
            hideType: true
        );

        // === VALUE ===
        $children['value'] = $dumper->normalize(
            $cookie->getValue(),
            "$path.value",
            $depth + 1,
            hideType: true
        );

        // === ATTRIBUTES ===
        // Expires
        $expiresTimestamp = $cookie->getExpiresTimestamp();
        if ($expiresTimestamp !== 0) {
            $children['expires'] = $dumper->normalize(
                $cookie->getExpiresString(),
                "$path.expires",
                $depth + 1,
                hideType: true
            );
        }

        // Path
        $children['path'] = $dumper->normalize(
            $cookie->getPath(),
            "$path.path",
            $depth + 1,
            hideType: true
        );

        // Domain
        if ($cookie->getDomain() !== '') {
            $children['domain'] = $dumper->normalize(
                $cookie->getDomain(),
                "$path.domain",
                $depth + 1,
                hideType: true
            );
        }

        // Secure
        $children['secure'] = $dumper->normalize(
            $cookie->isSecure(),
            "$path.secure",
            $depth + 1,
            hideType: true
        );

        // HttpOnly
        $children['httponly'] = $dumper->normalize(
            $cookie->isHttpOnly(),
            "$path.httponly",
            $depth + 1,
            hideType: true
        );

        // SameSite
        $children['samesite'] = $dumper->normalize(
            $cookie->getSameSite(),
            "$path.samesite",
            $depth + 1,
            hideType: true
        );

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Cookie',
            summary: "Cookie: {$cookie->getName()}",
            children: $children,
            meta: ['path' => $path]
        );
    }
}
