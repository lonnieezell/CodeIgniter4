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

use CodeIgniter\Debug\Dump\CasterInterface;
use CodeIgniter\Debug\Dump\Dumper;
use CodeIgniter\Debug\Dump\Node;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Custom caster for RedirectResponse objects.
 * Displays redirect location, status code, and any additional data.
 */
class RedirectResponseCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle RedirectResponse objects
        if (!$value instanceof RedirectResponse) {
            return null;
        }

        return $this->castRedirectResponse($value, $path, $depth, $dumper);
    }

    /**
     * Cast a RedirectResponse object.
     */
    private function castRedirectResponse(RedirectResponse $response, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === STATUS ===
        $statusCode = $response->getStatusCode();
        $reasonPhrase = $response->getReasonPhrase();
        $children['status'] = $dumper->normalize(
            "{$statusCode} {$reasonPhrase}",
            "$path.status",
            $depth + 1,
            hideType: true
        );

        // === LOCATION ===
        $location = $response->getHeaderLine('location');
        if ($location) {
            $children['location'] = $dumper->normalize(
                $location,
                "$path.location",
                $depth + 1,
                hideType: true
            );
        }

        // === HEADERS ===
        $headerArray = [];
        foreach ($response->headers() as $name => $header) {
            if (is_object($header) && method_exists($header, 'getValueLine')) {
                $headerArray[$name] = $header->getValueLine();
            } else {
                $headerArray[$name] = (string) $header;
            }
        }
        if (!empty($headerArray)) {
            $children['headers'] = $dumper->normalize($headerArray, "$path.headers", $depth + 1);
        }

        // === COOKIES ===
        $cookies = $response->getCookies();
        if (!empty($cookies)) {
            $cookieNodes = [];
            foreach ($cookies as $cookie) {
                // Use CookieCaster to format each cookie consistently
                $caster = new CookieCaster();
                $cookieNode = $caster->cast($cookie, "$path.cookies.{$cookie->getName()}", $depth + 1, $dumper);
                if ($cookieNode !== null) {
                    $cookieNodes[$cookie->getName()] = $cookieNode;
                }
            }
            if (!empty($cookieNodes)) {
                $children['cookies'] = $dumper->normalize($cookieNodes, "$path.cookies", $depth + 1);
            }
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'RedirectResponse',
            summary: "RedirectResponse ({$statusCode})",
            children: $children,
            meta: ['path' => $path]
        );
    }
}
