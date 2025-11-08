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
use CodeIgniter\Superglobals;

/**
 * Custom caster for Superglobals instances.
 * Displays $_SERVER and $_GET superglobal data.
 */
class SuperGlobalsCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Superglobals objects
        if (!$value instanceof Superglobals) {
            return null;
        }

        return $this->castSuperglobals($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Superglobals object.
     */
    private function castSuperglobals(Superglobals $superglobals, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        try {
            // Use reflection to access protected properties
            $reflection = new \ReflectionClass($superglobals);

            // Get $_SERVER data
            $serverProperty = $reflection->getProperty('server');
            $serverProperty->setAccessible(true);
            $server = $serverProperty->getValue($superglobals);

            if (!empty($server)) {
                $serverChildren = [];
                foreach ($server as $key => $value) {
                    $serverChildren[$key] = $dumper->normalize(
                        $value,
                        "$path.server[$key]",
                        $depth + 1
                    );
                }
                $children['$_SERVER'] = new Node(
                    kind: Node::KIND_ARRAY,
                    type: 'array',
                    summary: 'array(' . count($serverChildren) . ')',
                    children: $serverChildren,
                    hideType: true
                );
            } else {
                $children['$_SERVER'] = new Node(
                    kind: Node::KIND_ARRAY,
                    type: 'array',
                    summary: 'array(0)',
                    hideType: true
                );
            }

            // Get $_GET data
            $getProperty = $reflection->getProperty('get');
            $getProperty->setAccessible(true);
            $get = $getProperty->getValue($superglobals);

            if (!empty($get)) {
                $getChildren = [];
                foreach ($get as $key => $value) {
                    $getChildren[$key] = $dumper->normalize(
                        $value,
                        "$path.get[$key]",
                        $depth + 1
                    );
                }
                $children['$_GET'] = new Node(
                    kind: Node::KIND_ARRAY,
                    type: 'array',
                    summary: 'array(' . count($getChildren) . ')',
                    children: $getChildren,
                    hideType: true
                );
            } else {
                $children['$_GET'] = new Node(
                    kind: Node::KIND_ARRAY,
                    type: 'array',
                    summary: 'array(0)',
                    hideType: true
                );
            }

        } catch (\Throwable $e) {
            $children['_error'] = new Node(
                kind: Node::KIND_SCALAR,
                type: 'string',
                summary: "Error: " . $e->getMessage(),
                hideType: true
            );
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Superglobals',
            summary: 'Superglobals: $_SERVER, $_GET',
            children: $children,
            hideType: true
        );
    }
}
