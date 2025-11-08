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

/**
 * Custom caster for Stack Traces (arrays of trace frames).
 * Displays call stacks with enhanced formatting including:
 * - Relative file paths
 * - Color-coded method types (-> vs ::)
 * - Hierarchical display
 */
class TraceCaster implements CasterInterface
{
    /**
     * Project root path for calculating relative paths
     */
    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(dirname(dirname(dirname(dirname(__DIR__))))) . DIRECTORY_SEPARATOR;
    }

    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle trace arrays - they should be arrays of arrays with specific keys
        if (!is_array($value) || empty($value)) {
            return null;
        }

        // Check if this looks like a trace by examining the first element
        $firstElement = reset($value);
        if (!is_array($firstElement) || !isset($firstElement['file'], $firstElement['line'], $firstElement['function'])) {
            return null;
        }

        return $this->castTrace($value, $path, $depth, $dumper);
    }

    /**
     * Cast a trace array.
     */
    private function castTrace(array $trace, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        foreach ($trace as $idx => $frame) {
            $file = $frame['file'] ?? 'internal';
            $line = $frame['line'] ?? 0;
            $class = $frame['class'] ?? null;
            $type = $frame['type'] ?? null;
            $function = $frame['function'] ?? null;

            // Build the call signature with colors
            $callSignature = $this->buildCallSignature($class, $type, $function);

            // Make file path relative
            $displayFile = $this->makeRelativePath((string) $file);

            // Ensure displayFile is a valid string
            if (!is_string($displayFile) || $displayFile === '') {
                $displayFile = 'unknown';
            }

            // Create the frame summary with file and line
            $frameSummary = "<span class='ci-trace-file'>" . htmlspecialchars($displayFile) . "</span> <span class='ci-trace-line'>:{$line}</span> → {$callSignature}";

            $frameChildren = [];

            // Add expandable details
            $framePath = $path . '[' . $idx . ']';

            $frameChildren['file'] = $dumper->normalize(
                $file,
                $framePath . '.file',
                $depth + 1
            );

            $frameChildren['line'] = $dumper->normalize(
                $line,
                $framePath . '.line',
                $depth + 1
            );

            if ($class) {
                $frameChildren['class'] = $dumper->normalize(
                    $class,
                    $framePath . '.class',
                    $depth + 1
                );
            }

            if ($function) {
                $frameChildren['function'] = $dumper->normalize(
                    $function,
                    $framePath . '.function',
                    $depth + 1
                );
            }

            $children[] = new Node(
                kind: Node::KIND_OBJECT,
                type: 'Frame',
                summary: $frameSummary,
                children: $frameChildren,
                hideType: true
            );
        }

        return new Node(
            kind: Node::KIND_ARRAY,
            type: 'array',
            summary: "Stack Trace: " . count($children) . " frame" . (count($children) !== 1 ? "s" : ""),
            children: $children,
            hideType: true,
            meta: ['kind' => 'trace']
        );
    }

    /**
     * Build a color-coded call signature.
     */
    private function buildCallSignature(?string $class, ?string $type, ?string $function): string
    {
        if (!$function) {
            return "(internal)";
        }

        if (!$class) {
            return "<span class='ci-method-name'>{$function}()</span>";
        }

        // Color code based on call type
        // Note: $type is typically '->' or '::'
        $isStatic = strpos($type ?? '', ':') !== false;

        if ($isStatic) {
            return "<span class='ci-method-name'>{$class}</span><span class='ci-method-args'>::</span><span class='ci-method-name'>{$function}()</span>";
        } else {
            return "<span class='ci-method-name'>{$class}</span><span class='ci-method-args'>-></span><span class='ci-method-name'>{$function}()</span>";
        }
    }

    /**
     * Convert absolute path to relative path from project root.
     */
    private function makeRelativePath(string $file): string
    {
        if (str_starts_with($file, $this->projectRoot)) {
            $relative = substr($file, strlen($this->projectRoot));
            return $relative !== '' ? $relative : $file;
        }

        return $file;
    }
}
