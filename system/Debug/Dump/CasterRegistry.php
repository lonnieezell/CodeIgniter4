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

use CodeIgniter\Debug\Dump\CasterInterface;

/**
 * The CasterRegistry locates and manages casters for dumping objects.
 *
 * System-provider casters are expected to be found at System\Debug\Dump\Casters\<ClassName>Caster.
 * User-defined casters can be registered via the CasterRegistry::add method.
 */
final class CasterRegistry
{
    private array $casters = [];

    public function __construct()
    {
        $this->collectSystemCasters();
    }

    /**
     * Returns the list of registered casters.
     */
    public function getCasters(): array
    {
        return $this->casters;
    }

    /**
     * Registers a new caster.
     */
    public function add(CasterInterface $caster): void
    {
        $this->casters[] = $caster;
    }

    /**
     * Attempts to cast a value using the registered casters.
     */
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        foreach ($this->casters as $caster) {
            if ($node = $caster->cast($value, $path, $depth, $dumper)) {
                return $node;
            }
        }

        return null;
    }

    /**
     * Initializes the registry by collecting system casters.
     */
    private function collectSystemCasters(): void
    {
        $casterDir = __DIR__ . '/Casters';

        // First, load all caster files from the Casters directory
        if (is_dir($casterDir)) {
            foreach (glob($casterDir . '/*.php') as $file) {
                require_once $file;
            }
        }

        $casterNamespace = __NAMESPACE__ . '\\Casters\\';
        $declaredClasses = get_declared_classes();

        foreach ($declaredClasses as $class) {
            if (str_starts_with($class, $casterNamespace) && is_subclass_of($class, CasterInterface::class)) {
                $this->add(new $class());
            }
        }
    }
}
