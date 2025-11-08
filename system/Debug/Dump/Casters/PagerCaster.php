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
use CodeIgniter\Pager\Pager;

/**
 * Custom caster for Pager instances.
 * Displays pagination information including current page, total items, page count, etc.
 */
class PagerCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Pager objects
        if (!$value instanceof Pager) {
            return null;
        }

        return $this->castPager($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Pager object.
     */
    private function castPager(Pager $pager, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // Get groups using reflection since groups property is protected
        $reflection = new \ReflectionClass($pager);
        $groupsProperty = $reflection->getProperty('groups');
        $groupsProperty->setAccessible(true);
        $groups = $groupsProperty->getValue($pager);

        if (!empty($groups)) {
            foreach ($groups as $groupName => $groupData) {
                $groupChildren = [];

                // Current page
                $currentPage = $groupData['currentPage'] ?? 1;
                $groupChildren['currentPage'] = $dumper->normalize(
                    $currentPage,
                    "$path.groups[$groupName].currentPage",
                    $depth + 1
                );

                // Per page
                $perPage = $groupData['perPage'] ?? 0;
                $groupChildren['perPage'] = $dumper->normalize(
                    $perPage,
                    "$path.groups[$groupName].perPage",
                    $depth + 1
                );

                // Total items
                $total = $groupData['total'] ?? 0;
                $groupChildren['total'] = $dumper->normalize(
                    $total,
                    "$path.groups[$groupName].total",
                    $depth + 1
                );

                // Page count
                $pageCount = $groupData['pageCount'] ?? 0;
                $groupChildren['pageCount'] = $dumper->normalize(
                    $pageCount,
                    "$path.groups[$groupName].pageCount",
                    $depth + 1
                );

                // Has more pages?
                $hasMore = ($currentPage * $perPage) < $total;
                $groupChildren['hasMore'] = $dumper->normalize(
                    $hasMore,
                    "$path.groups[$groupName].hasMore",
                    $depth + 1,
                    hideType: true
                );

                // URI path
                if (isset($groupData['uri'])) {
                    $uriPath = (string)$groupData['uri'];
                    $groupChildren['uri'] = $dumper->normalize(
                        $uriPath,
                        "$path.groups[$groupName].uri",
                        $depth + 1
                    );
                }

                $children[$groupName] = new Node(
                    kind: Node::KIND_OBJECT,
                    type: 'Group',
                    summary: "Group: Page {$currentPage} of {$pageCount} ({$total} items)",
                    children: $groupChildren,
                    hideType: true
                );
            }
        } else {
            $children['_note'] = new Node(
                kind: Node::KIND_SCALAR,
                type: 'string',
                summary: "string(0) ''",
                hideType: true
            );
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Pager',
            summary: 'Pager: ' . count($groups) . ' group' . (count($groups) !== 1 ? 's' : ''),
            children: $children,
            hideType: true
        );
    }
}
