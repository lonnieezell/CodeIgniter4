<?php

declare(strict_types=1);

/**
 * This file is p}

if (! function_exists('trace')) { framework.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use CodeIgniter\Debug\Dump\Dump;

if (! function_exists('d')) {
    /**
     * Dumps the given variables using the appropriate renderer for the current environment.
     *
     * Exits early if the environment is protected.
     *
     * @param mixed ...$vars Variables to dump
     * @return string The rendered dump output
     */
    function d(...$vars): void
    {
        $dump = is_cli() ? Dump::cli() : Dump::html();

        echo $dump->dump(...$vars);
    }
}

if (! function_exists('dd')) {
    /**
     * Dumps the given variables and exits if the environment is not protected.
     *
     * Exits early if the environment is protected.
     *
     * @param mixed ...$vars Variables to dump
     * @return void
     */
    function dd(...$vars): void
    {
        $dump = is_cli() ? Dump::cli() : Dump::html();
        $output = $dump->dump(...$vars);

        if (is_cli()) {
            fwrite(STDOUT, $output . PHP_EOL);
        } else {
            echo $output;
        }

        exit(1);
    }
}

if (! function_exists('trace')) {
    /**
     * Captures and dumps a stack trace using the appropriate renderer for the current environment.
     *
     * Exits early if the environment is protected.
     *
     * @param int $limit The maximum number of frames to capture (default 10)
     * @param int $skip The number of frames to skip from the top (default 0)
     * @return string The rendered trace output
     */
    function trace(int $limit = 10, int $skip = 0): string
    {
        $dump = is_cli() ? Dump::cli() : Dump::html();
        return $dump->trace($limit, $skip + 1);
    }
}
