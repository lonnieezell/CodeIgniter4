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
use CodeIgniter\Throttle\Throttler;

/**
 * Custom caster for Throttler instances.
 * Displays throttling state including token time and cache information.
 */
class ThrottlerCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Throttler objects
        if (!$value instanceof Throttler) {
            return null;
        }

        return $this->castThrottler($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Throttler object.
     */
    private function castThrottler(Throttler $throttler, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        try {
            // Token time - seconds until next token is available
            $tokenTime = $throttler->getTokenTime();
            $children['tokenTime'] = $dumper->normalize(
                $tokenTime,
                "$path.tokenTime",
                $depth + 1
            );

            // Display a human-readable status
            if ($tokenTime === 0) {
                $statusSummary = "✓ No throttling active";
            } else {
                $statusSummary = "⏱ Throttled - {$tokenTime}s until next token";
            }

            $children['status'] = new Node(
                kind: Node::KIND_SCALAR,
                type: 'string',
                summary: $statusSummary,
                hideType: true
            );

            // Current time (for reference)
            $children['currentTime'] = $dumper->normalize(
                $throttler->time(),
                "$path.currentTime",
                $depth + 1
            );

            // Use reflection to access protected cache property
            $reflection = new \ReflectionClass($throttler);
            $cacheProperty = $reflection->getProperty('cache');
            $cacheProperty->setAccessible(true);
            $cache = $cacheProperty->getValue($throttler);

            $cacheClass = class_basename($cache);
            $children['cache'] = $dumper->normalize(
                $cacheClass,
                "$path.cache",
                $depth + 1,
                hideType: true
            );

        } catch (\Throwable $e) {
            $children['_error'] = new Node(
                kind: Node::KIND_SCALAR,
                type: 'string',
                summary: "Error: " . $e->getMessage(),
                hideType: true
            );
        }

        $summary = $tokenTime === 0
            ? 'Throttler: No throttling'
            : "Throttler: Throttled ({$tokenTime}s)";

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Throttler',
            summary: $summary,
            children: $children,
            hideType: true
        );
    }
}
