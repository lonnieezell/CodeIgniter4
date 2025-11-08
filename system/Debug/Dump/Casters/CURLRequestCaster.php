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
use CodeIgniter\HTTP\CURLRequest;

/**
 * Custom caster for CURLRequest objects.
 * Displays the current request configuration and state.
 */
class CURLRequestCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle CURLRequest objects
        if (!$value instanceof CURLRequest) {
            return null;
        }

        return $this->castCURLRequest($value, $path, $depth, $dumper);
    }

    /**
     * Cast a CURLRequest object.
     */
    private function castCURLRequest(CURLRequest $request, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === METHOD ===
        try {
            $method = $request->getMethod();
            $children['method'] = $dumper->normalize(
                $method,
                "$path.method",
                $depth + 1,
                hideType: true
            );
        } catch (\Throwable) {
            // Method may not be set yet
        }

        // === URL / URI ===
        try {
            $uri = $request->getUri();
            $children['uri'] = $dumper->normalize(
                (string) $uri,
                "$path.uri",
                $depth + 1,
                hideType: true
            );
        } catch (\Throwable) {
            // URI may not be set yet
        }

        // === HEADERS ===
        try {
            $headerArray = [];
            foreach ($request->headers() as $name => $header) {
                if (is_object($header) && method_exists($header, 'getValueLine')) {
                    $headerArray[$name] = $header->getValueLine();
                } else {
                    $headerArray[$name] = (string) $header;
                }
            }
            if (!empty($headerArray)) {
                $children['headers'] = $dumper->normalize($headerArray, "$path.headers", $depth + 1);
            }
        } catch (\Throwable) {
            // Headers may not be accessible
        }

        // === BODY ===
        try {
            $body = $request->getBody();
            if ($body !== null && $body !== '') {
                $bodyStr = (string) $body;
                $bodyPreview = mb_strlen($bodyStr) > 200
                    ? mb_substr($bodyStr, 0, 200) . '…'
                    : $bodyStr;

                $children['body'] = $dumper->normalize(
                    $bodyPreview,
                    "$path.body",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // Body may not be accessible
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'CURLRequest',
            summary: 'CURLRequest',
            children: $children,
            meta: ['path' => $path]
        );
    }
}
