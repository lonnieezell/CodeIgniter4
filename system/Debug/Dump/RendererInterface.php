<?php
namespace CodeIgniter\Debug\Dump;

/**
 * This file is part of CodeIgniter 4 framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use CodeIgniter\Debug\Dump\Node;

interface RendererInterface
{
    /** @param list<Node> $roots */
    public function render(array $roots): string;
}
