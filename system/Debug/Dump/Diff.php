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

namespace CodeIgniter\Debug\Dump;

final class Diff
{
    /**
     * Very small, type-aware shallow diff: keys added/removed/changed.
     * Returns a Node for rendering like any other dump.
     */
    public static function diff(mixed $a, mixed $b, Dumper $dumper): Node
    {
        // Only arrays/objects shallowly; fall back to scalar compare
        $nodeA = is_object($a) ? get_object_vars($a) : (array)$a;
        $nodeB = is_object($b) ? get_object_vars($b) : (array)$b;

        $added   = array_diff_key($nodeB, $nodeA);
        $removed = array_diff_key($nodeA, $nodeB);
        $common  = array_intersect_key($nodeA, $nodeB);

        $children = [];

        if ($added) {
            $group = [];

            foreach ($added as $key => $value) {
                $group[(string)$key] = $dumper->normalize($value, "diff.added.$key", 0);
            }

            $children['+ added'] = new Node(Node::KIND_ARRAY, 'array', 'added', $group);
        }

        if ($removed) {
            $group = [];

            foreach ($removed as $key => $value) {
                $group[(string)$key] = $dumper->normalize($value, "diff.removed.$key", 0);
            }

            $children['- removed'] = new Node(Node::KIND_ARRAY, 'array', 'removed', $group);
        }

        $changedGroup = [];

        foreach ($common as $key => $valueA) {
            $valueB = $nodeB[$key];

            if (self::scalarEquals($valueA, $valueB)) {
                continue;
            }

            $changedGroup[(string)$key] = new Node(
                kind: Node::KIND_ARRAY,
                type: 'pair',
                summary: 'changed',
                children: [
                    'from' => $dumper->normalize($valueA, "diff.changed.$key.from", 0),
                    'to'   => $dumper->normalize($valueB, "diff.changed.$key.to", 0),
                ]
            );
        }
        if ($changedGroup) {
            $children['~ changed'] = new Node(
                kind: Node::KIND_ARRAY,
                type: 'array',
                summary: 'changed',
                children: $changedGroup
            );
        }

        return new Node(
            kind: Node::KIND_ARRAY,
            type: 'diff',
            summary: 'diff',
            children: $children
        );
    }

    /**
     * Compares two scalar values for equality.
     */
    private static function scalarEquals(mixed $a, mixed $b): bool
    {
        if (is_scalar($a) && is_scalar($b)) {
            return $a === $b;
        }

        return false;
    }
}
