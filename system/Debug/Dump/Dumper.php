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

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Config\Debug;
use SplObjectStorage;
use CodeIgniter\Debug\Dump\CasterRegistry;

final class Dumper
{
    /**
     * @var Debug $config
     */
    private BaseConfig $config;
    private CasterRegistry $casters;
    private SplObjectStorage $seen;

    public function __construct(?BaseConfig $config, ?CasterRegistry $casters)
    {
        $this->config = $config ?? config('Debug');
        $this->casters = $casters ?? new CasterRegistry();
        $this->seen = new SplObjectStorage();
    }

    /**
     * Get the CasterRegistry instance.
     */
    public function casters(): CasterRegistry
    {
        return $this->casters;
    }

    /**
     * Given an object will convert it into one ore more Nodes
     * that can be passed into the Renderers.
     */
    public function normalize(mixed $value, string $path='root', int $depth=0, bool $hideType=false): Node
    {
        // Bail if we've reached max depth
        if ($depth >= $this->config->dumpMaxDepth) {
            return new Node(
                kind: Node::KIND_UNKNOWN,
                type: get_debug_type($value),
                summary: '… (max depth reached)',
                meta: ['path' => $path]
            );
        }

        if (is_null($value)) {
            return new Node(
                kind: Node::KIND_NULL,
                type: 'null',
                summary: 'null',
                meta: ['path' => $path]
            );
        }

        // Process any custom casters first
        if ($cast = $this->casters->cast($value, $path, $depth, $this)) {
            return $cast;
        }

        // Still here? Handle standard types
        $type = get_debug_type($value);

        if (is_string($value)) {
            return $this->stringNode($value, $path, $hideType);
        }

        if (is_scalar($value)) {
            return $this->scalarNode($value, $type, $path, $hideType);
        }

        if (is_array($value)) {
            return $this->arrayNode($value, $path, $depth);
        }

        if (is_object($value)) {
            return $this->objectNode($value, $type, $path, $depth);
        }

        if (is_resource($value)) {
            $meta = @stream_get_meta_data($value) ?: [];
            $summary  = sprintf('resource(%s)%s', get_resource_type($value), isset($meta['uri']) ? " {$meta['uri']}" : '');
            return new Node(
                kind: Node::KIND_RESOURCE,
                type: $type,
                summary: $summary,
                meta: ['path'=>$path] + $meta);
        }

        return new Node(Node::KIND_UNKNOWN, $type, $type, [], ['path'=>$path]);
    }

    /**
     * Create a Node for scalar objects (boolean, int, float, string).
     */
    private function scalarNode(mixed $value, string $type, string $path, bool $hideType = false): Node
    {
        $summary = match (true) {
            is_bool($value)  => $value ? 'true' : 'false',
            is_int($value)   => "int({$value})",
            is_float($value) => "float({$value})",
            default          => (string)$value
        };

        if ($hideType) {
            $summary = (string)$value;
        }

        return new Node(
            kind: Node::KIND_SCALAR,
            type: $type,
            summary: $summary,
            meta: ['path'=>$path],
            hideType: $hideType
        );
    }

    /**
     * Create a Node for strings.
     */
    private function stringNode(string $value, string $path, bool $hideType = false): Node
    {
        $len = mb_strlen($value);
        $max = $this->config->dumpMaxStringLength ?? 512;
        $tr  = $len > $max ? mb_substr($value, 0, $max) . '…' : $value;
        $sum = $hideType
            ? $this->preview($tr)
            : sprintf("string(%d) '%s'", $len, $this->preview($tr));

        return new Node(
            kind: Node::KIND_SCALAR,
            type: 'string',
            summary: $sum,
            meta: ['path'=>$path, 'length'=>$len, 'truncated'=>$len > $max],
            hideType: $hideType
        );
    }

    /**
     * Create a Node for arrays.
     */
    private function arrayNode(array $arr, string $path, int $depth): Node
    {
        // Check if this array looks like a table (array of associative arrays with consistent keys)
        if ($this->isTableLike($arr)) {
            return $this->tableNode($arr, $path, $depth);
        }

        $children = [];
        $i = 0;
        $max = $this->config->dumpMaxItems;

        foreach ($arr as $key => $v) {
            $keyStr = is_int($key) ? (string)$key : (string)$key;

            // Only first depth on array in safe mode. Redaction by key name
            if (!$this->config->dumpSafeMode && $this->config->shouldRedact($keyStr)) {
                $children[$keyStr] = new Node(
                    kind: Node::KIND_SCALAR,
                    type: 'redacted',
                    summary: $this->redactValue($v),
                    meta: ['path'=>"$path.$keyStr", 'redacted'=>true]
                );

                continue;
            }

            if ($i++ >= $max) {
                $children['…'] = new Node(
                    kind: Node::KIND_UNKNOWN,
                    type: 'more',
                    summary: sprintf('… (%d more)', count($arr) - $max),
                    meta: ['path'=>$path]
                );
                break;
            }

            $childPath         = is_int($key) ? "{$path}[{$key}]" : "{$path}.{$key}";
            $children[$keyStr] = $this->normalize($v, $childPath, $depth+1);
        }

        return new Node(
            kind: Node::KIND_ARRAY,
            type: 'array',
            summary: sprintf('array(%d)', count($arr)),
            children: $children,
            meta: ['path' => $path]
        );
    }

    /**
     * Detect if an array looks like tabular data (array of associative arrays with consistent keys).
     */
    private function isTableLike(array $arr): bool
    {
        // Need at least 2 rows to be considered table-like
        if (count($arr) < 2) {
            return false;
        }

        // Check if most rows are associative arrays
        $rowCount = 0;
        $assocCount = 0;
        $firstKeys = null;

        foreach ($arr as $row) {
            $rowCount++;
            if (!is_array($row)) {
                return false;
            }

            // Check if this is an associative array
            $keys = array_keys($row);
            if ($keys !== array_keys(array_values($row))) {
                $assocCount++;
            }

            // All rows should have the same keys
            if ($firstKeys === null) {
                $firstKeys = $keys;
            } elseif ($keys !== $firstKeys) {
                return false;
            }

            // Stop checking after first 10 rows for performance
            if ($rowCount >= 10) {
                break;
            }
        }

        // Most rows should be associative arrays for this to be table-like
        return $assocCount >= ($rowCount * 0.8);
    }

    /**
     * Create a table Node from an array of associative arrays.
     */
    private function tableNode(array $arr, string $path, int $depth): Node
    {
        // Get column names from first row
        $firstRow = reset($arr);
        $columns = array_keys($firstRow);

        // Prepare rows - convert each to normalized values
        $rows = [];
        $rowNum = 0;
        $max = $this->config->dumpMaxItems;

        foreach ($arr as $rowData) {
            if ($rowNum++ >= $max) {
                break;
            }

            $row = [];
            foreach ($columns as $col) {
                $value = $rowData[$col] ?? null;
                $rowPath = "{$path}[{$rowNum}].{$col}";
                $row[$col] = $this->normalize($value, $rowPath, $depth + 1);
            }
            $rows[] = $row;
        }

        return new Node(
            kind: Node::KIND_TABLE,
            type: 'array',
            summary: sprintf('array(%d)', count($arr)),
            children: [], // Tables don't use children, they use meta for data
            meta: [
                'path' => $path,
                'columns' => $columns,
                'rows' => $rows,
                'rowCount' => count($arr),
                'hasMore' => $rowNum > $max
            ]
        );
    }

    /**
     * Create a Node for objects.
     */
    private function objectNode(object $obj, string $type, string $path, int $depth): Node
    {
        if ($this->seen->contains($obj)) {
            return new Node(
                kind: Node::KIND_OBJECT,
                type: $type,
                summary: "{$type} {#recursion}",
                meta: ['path'=>$path, 'recursion'=>true]
            );
        }
        $this->seen->attach($obj);

        $props = [];
        $max   = $this->config->dumpMaxItems;
        $i     = 0;

        // Reflect without triggering magic if safeMode
        $ref = new \ReflectionObject($obj);
        foreach ($ref->getProperties() as $prop) {
            if ($i++ >= $max) {
                $props['…'] = new Node(
                    kind: Node::KIND_UNKNOWN,
                    type: 'more',
                    summary: '… (more props)',
                    meta: ['path'=>$path]
                );
                break;
            }

            $prop->setAccessible(true);
            $name = $prop->getName();

            if ($this->config->shouldRedact($name)) {
                $props[$name] = new Node(
                    kind: Node::KIND_SCALAR,
                    type: 'redacted',
                    summary: $this->redactValue($prop->getValue($obj)),
                    meta: ['path'=>"$path.$name", 'redacted'=>true]
                );
                continue;
            }

            $value  = null;
            try {
                $value = $prop->getValue($obj);
            } catch (\Throwable) {
                $props[$name] = new Node(
                    kind: Node::KIND_UNKNOWN,
                    type: 'inaccessible',
                    summary: '{inaccessible}',
                    meta: ['path' => "$path.$name"]
                );
                continue;
            }

            $props[$name] = $this->normalize($value, "{$path}.{$name}", $depth+1);
        }

        $summary = "{$type}";

        return new Node(
            kind: Node::KIND_OBJECT,
            type: $type,
            summary: $summary,
            children: $props,
            meta: ['path'=>$path]
        );
    }

    /**
     * Generate a preview of a string for summaries.
     */
    private function preview(string $value): string
    {
        $value = str_replace(["\r","\n"], ['␍','␤'], $value);

        return mb_strlen($value) > 120
            ? mb_substr($value, 0, 120) . '…'
            : $value;
    }

    /**
     * Redact a value by showing only the first and last few characters.
     */
    private function redactValue(mixed $value): string
    {
        if (is_string($value)) {
            $len = strlen($value);

            // For short strings, just show asterisks
            if ($len <= 3) {
                return '***';
            }

            // Show first character, asterisks, last character
            $first = $value[0];
            $last = $value[$len - 1];
            return $first . '***' . $last;
        }

        // For non-strings, just return asterisks
        return '***';
    }
}
