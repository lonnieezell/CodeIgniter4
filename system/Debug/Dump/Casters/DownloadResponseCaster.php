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
use CodeIgniter\HTTP\DownloadResponse;

/**
 * Custom caster for DownloadResponse objects.
 * Displays download filename, source, content type, and size.
 */
class DownloadResponseCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle DownloadResponse objects
        if (!$value instanceof DownloadResponse) {
            return null;
        }

        return $this->castDownloadResponse($value, $path, $depth, $dumper);
    }

    /**
     * Cast a DownloadResponse object.
     */
    private function castDownloadResponse(DownloadResponse $response, string $path, int $depth, Dumper $dumper): Node
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

        // === FILENAME ===
        try {
            $filename = $this->getFilename($response);
            $children['filename'] = $dumper->normalize(
                $filename,
                "$path.filename",
                $depth + 1,
                hideType: true
            );
        } catch (\Throwable) {
            // Filename may not be accessible
        }

        // === DOWNLOAD SOURCE ===
        $source = $this->getDownloadSource($response);
        $children['source'] = $dumper->normalize(
            $source,
            "$path.source",
            $depth + 1,
            hideType: true
        );

        // === CONTENT TYPE ===
        try {
            $contentType = $response->getHeaderLine('content-type');
            if ($contentType) {
                $children['contentType'] = $dumper->normalize(
                    $contentType,
                    "$path.contentType",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // Content type may not be available
        }

        // === CONTENT LENGTH ===
        try {
            $contentLength = $response->getContentLength();
            if ($contentLength > 0) {
                $children['contentLength'] = $dumper->normalize(
                    "{$contentLength} bytes",
                    "$path.contentLength",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // Content length may not be available
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

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'DownloadResponse',
            summary: "DownloadResponse ({$statusCode})",
            children: $children,
            meta: ['path' => $path]
        );
    }

    /**
     * Get the download filename using reflection since it's private.
     */
    private function getFilename(DownloadResponse $response): string
    {
        $reflection = new \ReflectionClass($response);
        $property = $reflection->getProperty('filename');
        $property->setAccessible(true);

        return $property->getValue($response);
    }

    /**
     * Get the download source type (File or Binary Data).
     */
    private function getDownloadSource(DownloadResponse $response): string
    {
        $reflection = new \ReflectionClass($response);

        // Check if file is set
        $fileProperty = $reflection->getProperty('file');
        $fileProperty->setAccessible(true);
        if ($fileProperty->getValue($response) !== null) {
            return 'File';
        }

        // Check if binary is set
        $binaryProperty = $reflection->getProperty('binary');
        $binaryProperty->setAccessible(true);
        if ($binaryProperty->getValue($response) !== null) {
            return 'Binary Data';
        }

        return 'Unknown';
    }
}
