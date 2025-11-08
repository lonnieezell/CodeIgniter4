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
 * Displays a stack trace within a debug dump.
 */
final class Trace
{
    /**
     * Captures the current stack trace.
     *
     * Returns an array of frames, which are the dumped
     * like any other array of data by the Dump class.
     */
    public static function capture(int $limit = 10, int $skip = 0): array
    {
        $raw = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + $skip + 1);
        $raw = array_slice($raw, $skip + 1); // skip current

        $frames = [];

        foreach ($raw as $frame) {
            $frames[] = [
                'file'     => $frame['file'] ?? 'internal',
                'line'     => $frame['line'] ?? 0,
                'class'    => $frame['class'] ?? null,
                'type'     => $frame['type'] ?? null,
                'function' => $frame['function'] ?? null,
            ];
        }

        return $frames;
    }
}
