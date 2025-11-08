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

/**
 * Custom caster for JSON-encoded strings.
 * Detects valid JSON strings and displays the decoded structure.
 */
class JsonCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle strings
        if (!is_string($value)) {
            return null;
        }

        // Try to decode the JSON
        try {
            $decoded = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // Not valid JSON, let normal string handling take over
            return null;
        }

        // If we get here, it's valid JSON
        return $this->castJsonString($value, $decoded, $path, $depth, $dumper);
    }

    /**
     * Cast a JSON string by decoding and displaying the structure.
     */
    private function castJsonString(
        string $original,
        mixed $decoded,
        string $path,
        int $depth,
        Dumper $dumper
    ): Node {
        // Normalize the decoded JSON structure directly
        $decodedNode = $dumper->normalize($decoded, "$path.decoded", $depth + 1);

        // Return the decoded node with JSON metadata
        return new Node(
            kind: $decodedNode->kind,
            type: 'json',
            summary: $decodedNode->summary . ' (JSON)',
            children: $decodedNode->children,
            meta: array_merge($decodedNode->meta ?? [], ['path' => $path, 'jsonBytes' => strlen($original)])
        );
    }
}
