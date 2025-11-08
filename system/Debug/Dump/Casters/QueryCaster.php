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

use CodeIgniter\Database\Query;
use CodeIgniter\Debug\Dump\CasterInterface;
use CodeIgniter\Debug\Dump\Dumper;
use CodeIgniter\Debug\Dump\Node;

/**
 * Custom caster for Query instances.
 * Displays the executed query with timing and error information.
 */
class QueryCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Query objects
        if (!$value instanceof Query) {
            return null;
        }

        return $this->castQuery($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Query object.
     */
    private function castQuery(Query $query, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === SQL QUERY ===
        $sql = $query->getQuery();
        $highlightedSql = $this->syntaxHighlight($sql);

        $children['sql'] = new Node(
            kind: Node::KIND_SCALAR,
            type: 'string',
            summary: $highlightedSql,
            meta: ['length' => strlen($sql), 'path' => "$path.sql"]
        );

        // === TIMING INFO ===
        // Format start time as readable datetime
        $startTimeRaw = (float) $query->getStartTime(true);
        $startDateTime = (new \DateTime())->setTimestamp((int) $startTimeRaw);
        $startDateTime->modify(sprintf('+%d microseconds', (int)(($startTimeRaw - (int)$startTimeRaw) * 1000000)));

        $children['startTime'] = $dumper->normalize(
            $startDateTime->format('Y-m-d H:i:s.u'),
            "$path.startTime",
            $depth + 1,
            hideType: true
        );

        $durationSeconds = (float) $query->getDuration(4);
        $durationMs = $durationSeconds * 1000;

        $children['duration'] = $dumper->normalize(
            sprintf('%g ms', $durationMs),
            "$path.duration",
            $depth + 1,
            hideType: true
        );

        // === ERROR INFO (if any) ===
        if ($query->hasError()) {
            $errorInfo = "[{$query->getErrorCode()}] {$query->getErrorMessage()}";
            $children['error'] = $dumper->normalize(
                $errorInfo,
                "$path.error",
                $depth + 1,
                hideType: true
            );
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Query',
            summary: 'Query',
            children: $children,
            meta: ['path' => $path]
        );
    }

    /**
     * Apply SQL syntax highlighting by making keywords bold.
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
