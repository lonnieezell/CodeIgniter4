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
use Throwable;

/**
 * Custom caster for Throwable objects (Exceptions, Errors, etc).
 * Displays exception/error message, code, file, line, and trace in a useful format.
 */
class ThrowableCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Throwable objects
        if (!$value instanceof Throwable) {
            return null;
        }

        return $this->castThrowable($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Throwable object.
     */
    private function castThrowable(Throwable $throwable, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === TYPE ===
        $exceptionClass = get_class($throwable);
        $children['type'] = $dumper->normalize(
            $exceptionClass,
            "$path.type",
            $depth + 1,
            hideType: true
        );

        // === MESSAGE ===
        $children['message'] = $dumper->normalize(
            $throwable->getMessage(),
            "$path.message",
            $depth + 1,
            hideType: true
        );

        // === CODE ===
        $code = $throwable->getCode();
        if ($code !== 0) {
            $children['code'] = $dumper->normalize(
                $code,
                "$path.code",
                $depth + 1,
                hideType: true
            );
        }

        // === LOCATION ===
        $file = $throwable->getFile();
        $line = $throwable->getLine();
        $children['location'] = $dumper->normalize(
            "{$file}:{$line}",
            "$path.location",
            $depth + 1,
            hideType: true
        );

        // === STACK TRACE ===
        $trace = $throwable->getTrace();
        if (!empty($trace)) {
            $traceFormatted = [];
            foreach ($trace as $index => $frame) {
                $function = $frame['function'] ?? 'unknown';
                $class = $frame['class'] ?? '';
                $type = $frame['type'] ?? '';
                $file = $frame['file'] ?? 'internal';
                $line = $frame['line'] ?? '?';

                // Format: ClassName->method() or function_name()
                if ($class) {
                    $callSite = "{$class}{$type}{$function}()";
                } else {
                    $callSite = "{$function}()";
                }

                $traceFormatted[] = "#{$index} {$callSite} at {$file}:{$line}";
            }

            $children['trace'] = $dumper->normalize(
                $traceFormatted,
                "$path.trace",
                $depth + 1
            );
        }

        // === PREVIOUS EXCEPTION ===
        if ($throwable->getPrevious() instanceof Throwable) {
            $caster = new self();
            $previousNode = $caster->cast($throwable->getPrevious(), "$path.previous", $depth + 1, $dumper);
            if ($previousNode !== null) {
                $children['previous'] = $previousNode;
            }
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Throwable',
            summary: "{$exceptionClass}: {$throwable->getMessage()}",
            children: $children,
            meta: ['path' => $path]
        );
    }
}
