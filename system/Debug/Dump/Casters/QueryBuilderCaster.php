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

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Debug\Dump\CasterInterface;
use CodeIgniter\Debug\Dump\Dumper;
use CodeIgniter\Debug\Dump\Node;
use ReflectionClass;
use Throwable;

/**
 * Custom caster for BaseBuilder query builder instances.
 * Displays formatted SQL with syntax highlighting as the main output.
 */
class QueryBuilderCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle BaseBuilder objects
        if (!$value instanceof BaseBuilder) {
            return null;
        }

        return $this->castBuilder($value, $path, $depth, $dumper);
    }

    /**
     * Cast a BaseBuilder query builder instance.
     */
    private function castBuilder(BaseBuilder $builder, string $path, int $depth, Dumper $dumper): Node
    {
        // Generate the compiled SQL query
        $compiledQuery = $this->getCompiledQuery($builder);
        $highlightedQuery = $this->syntaxHighlight($compiledQuery);

        return new Node(
            kind: Node::KIND_SCALAR,
            type: 'string',
            summary: $highlightedQuery,
            meta: ['length' => strlen($compiledQuery), 'path' => $path]
        );
    }

    /**
     * Get the compiled SQL query from the builder.
     * Handles both SELECT and other query types.
     */
    private function getCompiledQuery(BaseBuilder $builder): string
    {
        try {
            // Try to get a compiled SELECT query
            $reflection = new ReflectionClass($builder);

            // Check if there's a QBSelect (SELECT query)
            if ($reflection->hasProperty('QBSelect')) {
                $selectProp = $reflection->getProperty('QBSelect');
                $selectProp->setAccessible(true);
                $qbSelect = $selectProp->getValue($builder);

                if (!empty($qbSelect)) {
                    return $builder->getCompiledSelect(false);
                }
            }

            // Check if there's a QB method to compile INSERT/UPDATE/DELETE
            if (method_exists($builder, 'getCompiledInsert')) {
                return $builder->getCompiledInsert(false);
            }
            if (method_exists($builder, 'getCompiledUpdate')) {
                return $builder->getCompiledUpdate(false);
            }
            if (method_exists($builder, 'getCompiledDelete')) {
                return $builder->getCompiledDelete(false);
            }

            // Fallback to SELECT
            return $builder->getCompiledSelect(false);
        } catch (Throwable) {
            return '[Unable to compile query]';
        }
    }

    /**
     * Apply basic SQL syntax highlighting by making keywords bold.
     */
    private function syntaxHighlight(string $sql): string
    {
        // SQL keywords to highlight
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'NOT',
            'JOIN', 'LEFT', 'RIGHT', 'INNER', 'OUTER', 'ON',
            'GROUP', 'BY', 'ORDER', 'HAVING', 'LIMIT', 'OFFSET',
            'INSERT', 'INTO', 'VALUES', 'UPDATE', 'SET', 'DELETE',
            'DISTINCT', 'AS', 'UNION', 'ALL', 'CASE', 'WHEN', 'THEN', 'ELSE', 'END',
            'CREATE', 'TABLE', 'ALTER', 'DROP', 'INDEX',
            'IS', 'NULL', 'LIKE', 'IN', 'BETWEEN', 'EXISTS',
        ];

        $output = $sql;

        // Highlight keywords by making them bold and uppercase
        foreach ($keywords as $keyword) {
            $output = preg_replace(
                '/\b' . $keyword . '\b/i',
                '**' . strtoupper($keyword) . '**',
                $output
            );
        }

        return $output;
    }
}
