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
use CodeIgniter\Debug\Dump\Renderers\CliRenderer;
use CodeIgniter\Debug\Dump\Renderers\HtmlRenderer;

final class Dump
{
    private function __construct(
        private Dumper $dumper,
        private RendererInterface $renderer,
    ) {}

    /**
     * Creates a Dump instance for CLI output.
     */
    public static function cli(?BaseConfig $config = null, ?CasterRegistry $casters = null): self
    {
        return new self(new Dumper($config, $casters), new CliRenderer());
    }

    /**
     * Creates a Dump instance for HTML output.
     */
    public static function html(?BaseConfig $config = null, ?CasterRegistry $casters = null): self
    {
        return new self(new Dumper($config, $casters), new HtmlRenderer());
    }

    /**
     * Dumps the given variables and returns the rendered output.
     */
    public function dump(mixed ...$vars): string
    {
        $nodes = [];
        // callStackIndex 2 because: 0=extract, 1=dump, 2=caller
        $names = VariableNameExtractor::extract(2);

        foreach ($vars as $i => $v) {
            $varName = $names[$i] ?? null;
            $node = $this->dumper->normalize($v, "arg{$i}", 0);
            $node->name = $varName;
            $nodes[] = $node;
        }

        return $this->renderer->render($nodes);
    }

    /**
     * Captures and dumps a stack trace up to the given limit.
     */
    public function trace(int $limit = 10, int $skip = 0): string
    {
        $frames = Trace::capture($limit, $skip + 1);

        return $this->dump($frames);
    }

    // Compatibility-style helpers
    public static function d(...$vars): void
    {
        $out = is_cli()
            ? self::cli()->dump(...$vars)
            : self::html()->dump(...$vars);

        if (is_cli()) {
            fwrite(STDOUT, $out . PHP_EOL);
        } else {
            echo $out;
        }
    }

    public static function dd(...$vars): void
    {
        self::d(...$vars);
        exit(1);
    }
}
