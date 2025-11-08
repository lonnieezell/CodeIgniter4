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
 * Extracts variable names from function call source code.
 */
final class VariableNameExtractor
{
    /**
     * Extract variable names from a function call by parsing the source line.
     * Returns an array of variable names in the order they appear in the arguments.
     *
     * @return array<int, string|null>
     */
    public static function extract(int $callStackIndex = 1): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $callStackIndex + 3);

        if (!isset($trace[$callStackIndex])) {
            return [];
        }

        $frame = $trace[$callStackIndex];

        // We need file and line to read the source
        if (!isset($frame['file'], $frame['line'])) {
            return [];
        }

        $file = $frame['file'];
        $line = $frame['line'];

        // Read the source file
        if (!is_file($file)) {
            return [];
        }

        $source = file($file, FILE_SKIP_EMPTY_LINES);
        if (!isset($source[$line - 1])) {
            return [];
        }

        $callLine = $source[$line - 1];

        // Extract function name to find the call
        $functionName = $frame['function'] ?? null;
        if (!$functionName) {
            return [];
        }

        // Parse the arguments from the source line
        return self::parseArguments($callLine, $functionName);
    }

    /**
     * Parse argument names from a source line containing a function call.
     *
     * @return array<int, string|null>
     */
    private static function parseArguments(string $line, string $functionName): array
    {
        // Find the function call in the line - handle both spaces and no spaces
        $pattern = '/\b' . preg_quote($functionName, '/') . '\s*\(\s*(.+?)\s*\)/';

        if (!preg_match($pattern, $line, $matches)) {
            return [];
        }

        $args = $matches[1];
        $names = [];
        $current = '';
        $depth = 0;
        $inString = false;
        $stringChar = '';

        // Parse the arguments manually, respecting nested parens and strings
        for ($i = 0; $i < strlen($args); $i++) {
            $char = $args[$i];
            $prev = $i > 0 ? $args[$i - 1] : '';

            // Handle strings
            if (($char === '"' || $char === "'") && $prev !== '\\') {
                if (!$inString) {
                    $inString = true;
                    $stringChar = $char;
                } elseif ($char === $stringChar) {
                    $inString = false;
                }
                $current .= $char;
                continue;
            }

            if ($inString) {
                $current .= $char;
                continue;
            }

            // Track nested parentheses/brackets
            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                // Argument separator
                $names[] = self::extractName(trim($current));
                $current = '';
            } else {
                $current .= $char;
            }
        }

        // Don't forget the last argument
        if ($current !== '') {
            $names[] = self::extractName(trim($current));
        }

        return $names;
    }

    /**
     * Extract the variable name from an argument expression.
     * Examples: "$var" -> "var", "$obj->prop" -> "obj->prop", "123" -> null
     */
    private static function extractName(string $arg): ?string
    {
        // Skip if it's a literal or complex expression
        if (empty($arg) || $arg === '...') {
            return null;
        }

        // If it starts with $ it's likely a variable
        if ($arg[0] === '$') {
            // Remove the leading $
            $arg = ltrim($arg, '$');

            // Handle simple variables and property/method access
            // Match: varName or varName->prop or varName['key'] or varName[0] etc
            if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)/u', $arg, $matches)) {
                return $matches[1];
            }
        }

        // If it's a simple identifier without special chars, return it
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/u', $arg)) {
            return $arg;
        }

        // It's a literal or complex expression, return null
        return null;
    }
}
