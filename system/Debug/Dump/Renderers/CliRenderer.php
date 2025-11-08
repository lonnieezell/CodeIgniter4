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

namespace CodeIgniter\Debug\Dump\Renderers;

use CodeIgniter\Debug\Dump\RendererInterface;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Debug\Dump\Node;

/**
 * CLI Renderer for variable dumps.
 */
final class CliRenderer implements RendererInterface
{
    private string $indent = '  ';

    /**
     * Renders the given root nodes into a CLI-friendly manner.
     *
     * @param array<\CodeIgniter\Debug\Dump\Node> $roots
     */
    public function render(array $roots): string
    {
        $out = '';

        foreach ($roots as $root) {
            // Show variable name for root nodes if available
            if ($root->name !== null) {
                $out .= CLI::color($root->name . ':', 'red', null, 'bold') . PHP_EOL;
            }
            $out .= $this->renderNode($root, 0) . PHP_EOL;
        }

        return $out;
    }

    /**
     * Renders a single node and its children.
     */
    private function renderNode(Node $node, int $level): string
    {
        // Handle tables specially
        if ($node->kind === Node::KIND_TABLE) {
            return $this->renderTable($node, $level);
        }

        $pad = str_repeat($this->indent, $level);
        $line = $pad . $this->color($this->head($node), $node);

        if ($node->children) {
            foreach ($node->children as $k => $child) {
                $key = is_string($k) ? $k : (string)$k;
                $line .= PHP_EOL . $pad . $this->indent . $this->dim($key . ':');
                $line .= PHP_EOL . $this->renderNode($child, $level + 2);
            }
        }
        return $line;
    }

    /**
     * Render a table node in CLI format using CLI::table.
     */
    private function renderTable(Node $node, int $level): string
    {
        $meta = $node->meta;
        $columns = $meta['columns'] ?? [];
        $rows = $meta['rows'] ?? [];

        if (empty($columns) || empty($rows)) {
            $pad = str_repeat($this->indent, $level);
            return $pad . $this->color($this->head($node), $node);
        }

        $pad = str_repeat($this->indent, $level);

        // Build table rows - convert Node objects to strings, removing type info
        $tbody = [];
        foreach ($rows as $row) {
            $rowData = [];
            foreach ($columns as $col) {
                $cell = $row[$col] ?? null;
                $value = $cell instanceof Node ? $this->extractCellValue($cell) : (string)$cell;
                $rowData[] = $value;
            }
            $tbody[] = $rowData;
        }

        // Build header
        $thead = array_map('strval', $columns);

        // Capture CLI::table output
        ob_start();
        CLI::table($tbody, $thead);
        $table = ob_get_clean();

        // Indent the table
        $indentedTable = '';
        foreach (explode(PHP_EOL, $table) as $line) {
            if ($line !== '') {
                $indentedTable .= $pad . $line . PHP_EOL;
            }
        }

        return rtrim($indentedTable);
    }

    /**
     * Extract just the value from a cell Node, removing type information.
     */
    private function extractCellValue(Node $cell): string
    {
        $summary = $cell->summary;

        // Remove markdown bold markers
        $summary = preg_replace('/\*\*([^*]+)\*\*/', '$1', $summary);

        // For strings, extract just the quoted content: string(16) 'john@example.com' -> john@example.com
        if ($cell->kind === Node::KIND_SCALAR && $cell->type === 'string') {
            if (preg_match("/^string\\(\\d+\\)\\s+'([^']*)'$/", $summary, $matches)) {
                $summary = $matches[1];
            }
        }

        // For other scalars like int(1), just get the value part
        if ($cell->kind === Node::KIND_SCALAR && in_array($cell->type, ['int', 'float', 'bool'], true)) {
            if (preg_match("/^[a-z]+\\([^)]*\\)\\s+(.+)$/", $summary, $matches)) {
                $summary = $matches[1];
            }
        }

        // Truncate to 20 chars for better table fit
        return $this->truncateValue($summary, 20);
    }

    /**
     * Truncate a value to a max length for table display.
     */
    private function truncateValue(string $value, int $maxLen): string
    {
        if (strlen($value) > $maxLen) {
            return substr($value, 0, $maxLen - 1) . '…';
        }
        return $value;
    }

    /**
     * Returns the head string for a node.
     */
    private function head(Node $n): string
    {
        $summary = $this->formatMarkdown($n->summary);

        return match ($n->kind) {
            Node::KIND_ARRAY   => "[{$summary}]",
            Node::KIND_OBJECT  => "{$summary}",
            Node::KIND_SCALAR  => $summary,
            Node::KIND_RESOURCE=> $summary,
            Node::KIND_NULL    => 'null',
            default            => $summary,
        };
    }

    /**
     * Convert markdown bold (**text**) to CLI bold formatting.
     */
    private function formatMarkdown(string $text): string
    {
        return preg_replace_callback(
            '/\*\*([^*]+)\*\*/',
            static fn ($matches) => CLI::color($matches[1], 'white', null, 'bold'),
            $text
        );
    }

    /**
     * Applies color to the given text based on node kind.
     */
    private function color(string $text, Node $n): string
    {
        $colorMap = [
            Node::KIND_SCALAR   => 'green',
            Node::KIND_ARRAY    => 'blue',
            Node::KIND_OBJECT   => 'cyan',
            Node::KIND_RESOURCE => 'light_purple',
            Node::KIND_NULL     => 'light_gray',
        ];

        if (isset($colorMap[$n->kind])) {
            return CLI::color($text, $colorMap[$n->kind]);
        }

        return $text;
    }

    /**
     * Dim the given string for less emphasis.
     */
    private function dim(string $s): string
    {
        return CLI::color($s, 'light_gray');
    }
}
