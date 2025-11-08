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

use CodeIgniter\Debug\Dump\Dumper;
use CodeIgniter\Debug\Dump\Node;

interface CasterInterface
{
    /**
     * Return null to skip, otherwise return a Node that represents $value.
     * $path is a dotted path "root.users[0].email" for redaction context.
     */
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node;
}
