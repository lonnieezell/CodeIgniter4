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
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Custom caster for IncomingRequest to show only the most relevant debugging info.
 */
class IncomingRequestCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle IncomingRequest objects
        if (!$value instanceof IncomingRequest) {
            return null;
        }

        return $this->castRequest($value, $path, $depth, $dumper);
    }

    /**
     * Cast an IncomingRequest object.
     */
    private function castRequest(IncomingRequest $request, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === ESSENTIAL INFO ===
        $children['method'] = $dumper->normalize(
            $request->getMethod(),
            "$path.method",
            $depth + 1,
            hideType: true
        );
        $children['uri'] = $dumper->normalize(
            (string) $request->getUri(),
            "$path.uri",
            $depth + 1,
            hideType: true
        );
        $children['path'] = $dumper->normalize(
            $request->getPath(),
            "$path.path",
            $depth + 1,
            hideType: true
        );

        // === REQUEST DATA ===
        $get = $request->getGet();
        if ($get) {
            $children['query'] = $dumper->normalize($get, "$path.query", $depth + 1);
        }

        $post = $request->getPost();
        if ($post) {
            $children['post'] = $dumper->normalize($post, "$path.post", $depth + 1);
        }

        // === FILES ===
        $files = $request->getFiles();
        if (!empty($files)) {
            $fileData = [];
            foreach ($files as $name => $file) {
                $fileData[$name] = [
                    'name' => $file->getName(),
                    'type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            }
            $children['files'] = $dumper->normalize($fileData, "$path.files", $depth + 1);
        }

        // === HEADERS ===
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

        // === COOKIES ===
        $cookies = $request->getCookie();
        if ($cookies) {
            $children['cookies'] = $dumper->normalize($cookies, "$path.cookies", $depth + 1, hideType: true);
        }

        // === FLAGS ===
        $flags = [
            'isSecure' => $request->isSecure(),
            'isAJAX' => $request->isAJAX(),
            'isCLI' => $request->isCLI(),
        ];
        $children['flags'] = $dumper->normalize($flags, "$path.flags", $depth + 1);

        // === LOCALE ===
        $children['locale'] = $dumper->normalize(
            $request->getLocale(),
            "$path.locale",
            $depth + 1,
            hideType: true
        );

        // === PROTOCOL ===
        $children['protocol'] = $dumper->normalize(
            $request->getProtocolVersion(),
            "$path.protocol",
            $depth + 1,
            hideType: true
        );

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'IncomingRequest',
            summary: 'IncomingRequest',
            children: $children,
            meta: ['path' => $path]
        );
    }
}
