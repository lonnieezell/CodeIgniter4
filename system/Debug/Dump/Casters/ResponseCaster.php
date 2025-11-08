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
use CodeIgniter\HTTP\Response;

/**
 * Custom caster for Response to show only the most relevant debugging info.
 */
class ResponseCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Response objects
        if (!$value instanceof Response) {
            return null;
        }

        return $this->castResponse($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Response object.
     */
    private function castResponse(Response $response, string $path, int $depth, Dumper $dumper): Node
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

        // === CONTENT TYPE ===
        $contentType = $response->getHeaderLine('content-type');
        if ($contentType) {
            $children['contentType'] = $dumper->normalize(
                $contentType,
                "$path.contentType",
                $depth + 1,
                hideType: true
            );
        }

        // === BODY INFO ===
        $body = $response->getBody();
        if ($body !== null) {
            $bodyStr = (string) $body;
            $bodySize = strlen($bodyStr);
            $bodyPreview = mb_strlen($bodyStr) > 200
                ? mb_substr($bodyStr, 0, 200) . '…'
                : $bodyStr;

            $children['body'] = $dumper->normalize(
                [
                    'size' => "{$bodySize} bytes",
                    'preview' => $bodyPreview,
                ],
                "$path.body",
                $depth + 1
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

        // === PROTOCOL ===
        $children['protocol'] = $dumper->normalize(
            $response->getProtocolVersion(),
            "$path.protocol",
            $depth + 1,
            hideType: true
        );

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Response',
            summary: "Response ({$statusCode})",
            children: $children,
            meta: ['path' => $path]
        );
    }
}
