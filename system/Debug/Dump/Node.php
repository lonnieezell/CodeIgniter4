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

/**
 * Represents a node in a variable dump structure.
 */
final class Node {
    public const KIND_SCALAR   = 'scalar';
    public const KIND_ARRAY    = 'array';
    public const KIND_OBJECT   = 'object';
    public const KIND_RESOURCE = 'resource';
    public const KIND_NULL     = 'null';
    public const KIND_UNKNOWN  = 'unknown';
    public const KIND_TABLE    = 'table';  // for array data that should display as a table

    public function __construct(
        public string $kind,
        public string $type,          // php type/class name
        public string $summary,       // short one-liner (e.g., "string(42) 'lorem…'")
        /** @var array<string,Node> */
        public array $children = [],  // for arrays/objects
        public array $meta = [],      // misc (file,line, flags, length, etc)
        public ?string $name = null,  // variable name, if available
        public bool $hideType = false, // whether to show type (string, int, etc) in renderers
    ) {}
}
