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

use CodeIgniter\Debug\Dump\Node;
use CodeIgniter\Debug\Dump\RendererInterface;

final class HtmlRenderer implements RendererInterface
{
    public function __construct(private bool $inlineCss = true) {}

    /**
     * Renders the given root nodes into an HTML format.
     *
     * @param list<Node> $roots
     */
    public function render(array $roots): string
    {
        $body = '';

        foreach ($roots as $root) {
            $body .= $this->nodeHtml($root);
        }

        $css = $this->inlineCss
            ? '<style>'.$this->css().'</style>'
            : '';

        return <<<HTML
<div class="ci-dump">{$css}{$body}</div>
HTML;
    }

    /**
     * Recursively renders a single node into HTML.
     */
    private function nodeHtml(Node $node, string $label = ''): string
    {
        // Handle table nodes specially
        if ($node->kind === Node::KIND_TABLE) {
            return $this->tableHtml($node, $label);
        }

        // Check for image preview in metadata
        if (!empty($node->meta['imageUrl'])) {
            return $this->imageHtml($node, $label);
        }

        // Check if summary contains HTML markup (method signatures) or markdown bold
        $isHtmlSummary = str_contains($node->summary, '<span class=');
        $head = $isHtmlSummary
            ? $node->summary
            : htmlspecialchars($node->summary, ENT_QUOTES, 'UTF-8');

        // Convert markdown bold (**text**) to HTML <strong> tags
        $head = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $head);

        $label = $label !== ''
            ? '<span class="ci-key">'.htmlspecialchars($label).':</span> '
            : '';

        // Show variable name for root nodes if available
        if ($label === '' && $node->name !== null) {
            $label = '<span class="ci-varname">'.htmlspecialchars($node->name).':</span> ';
        }

        // Determine the value span - hide type if hideType is true
        $valueSpan = $node->hideType
            ? "<span>{$head}</span>"
            : "<span class='ci-{$node->kind}'>{$head}</span>";

        if (!$node->children) {
            return "<div class='ci-line'>{$label}{$valueSpan}</div>";
        }

        $children = '';

        foreach ($node->children as $key => $child) {
            $children .= $this->nodeHtml($child, (string)$key);
        }

        return <<<HTML
<details class="ci-node">
  <summary>{$label}{$valueSpan}</summary>
  <div class="ci-children">{$children}</div>
</details>
HTML;
    }

    /**
     * Render an image node with preview.
     */
    private function imageHtml(Node $node, string $label = ''): string
    {
        $imageUrl = $node->meta['imageUrl'] ?? null;

        if (!$imageUrl) {
            // Fall back to normal rendering if no URL - render as regular object
            $label = $label !== ''
                ? '<span class="ci-key">'.htmlspecialchars($label).':</span> '
                : '';

            if ($node->name !== null && $label === '') {
                $label = '<span class="ci-varname">'.htmlspecialchars($node->name).':</span> ';
            }

            $head = htmlspecialchars($node->summary, ENT_QUOTES, 'UTF-8');
            $valueSpan = "<span class='ci-{$node->kind}'>{$head}</span>";

            $children = '';
            foreach ($node->children as $key => $child) {
                $children .= $this->nodeHtml($child, (string)$key);
            }

            return <<<HTML
<details class="ci-node">
  <summary>{$label}{$valueSpan}</summary>
  <div class="ci-children">{$children}</div>
</details>
HTML;
        }

        $label = $label !== ''
            ? '<span class="ci-key">'.htmlspecialchars($label).':</span> '
            : '';

        if ($node->name !== null && $label === '') {
            $label = '<span class="ci-varname">'.htmlspecialchars($node->name).':</span> ';
        }

        $head = htmlspecialchars($node->summary, ENT_QUOTES, 'UTF-8');
        $valueSpan = "<span class='ci-{$node->kind}'>{$head}</span>";

        $children = '';
        foreach ($node->children as $key => $child) {
            $children .= $this->nodeHtml($child, (string)$key);
        }

        $imageUrlEscaped = htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<details class="ci-node ci-image-node">
  <summary>{$label}{$valueSpan}</summary>
  <div class="ci-children">
    <img src="{$imageUrlEscaped}" alt="Image Preview" class="ci-image-preview">
    {$children}
  </div>
</details>
HTML;
    }

    /**
     * Render a table cell - just the value without type info.
     */
    private function tableCell(Node $cell): string
    {
        // Get the summary text
        $summary = $cell->summary;

        // Convert markdown bold to HTML
        $summary = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $summary);

        // For strings, extract just the value: string(16) 'john@example.com' -> john@example.com
        if ($cell->kind === Node::KIND_SCALAR && $cell->type === 'string') {
            if (preg_match("/^string\\(\\d+\\)\\s+'([^']*)'$/", $summary, $matches)) {
                $summary = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
                return $summary;
            }
        }

        // For other scalars, try to extract just the value part
        if ($cell->kind === Node::KIND_SCALAR && in_array($cell->type, ['int', 'float', 'bool'], true)) {
            if (preg_match("/^[a-z]+\\([^)]*\\)\\s+(.+)$/", $summary, $matches)) {
                return htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            }
        }

        // Default: escape and return the summary
        return htmlspecialchars($summary, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Render a table node as an HTML table.
     */
    private function tableHtml(Node $node, string $label = ''): string
    {
        $meta = $node->meta;
        $columns = $meta['columns'] ?? [];
        $rows = $meta['rows'] ?? [];

        if (empty($columns) || empty($rows)) {
            return "<div class='ci-line'>{$label}" . htmlspecialchars($node->summary) . "</div>";
        }

        $label = $label !== ''
            ? '<span class="ci-key">'.htmlspecialchars($label).':</span> '
            : '';

        if ($node->name !== null && $label === '') {
            $label = '<span class="ci-varname">'.htmlspecialchars($node->name).':</span> ';
        }

        // Build header row
        $headerCells = '';
        foreach ($columns as $col) {
            $headerCells .= '<th>' . htmlspecialchars((string)$col) . '</th>';
        }

        // Build data rows
        $bodyRows = '';
        $rowIdx = 0;
        foreach ($rows as $row) {
            $rowClass = $rowIdx % 2 === 0 ? 'ci-table-even' : 'ci-table-odd';
            $bodyRows .= "<tr class='{$rowClass}'>";
            foreach ($columns as $col) {
                $cell = $row[$col] ?? null;
                $cellContent = $cell instanceof Node ? $this->tableCell($cell) : '';
                $bodyRows .= "<td>{$cellContent}</td>";
            }
            $bodyRows .= '</tr>';
            $rowIdx++;
        }

        $tableHtml = <<<HTML
<table class="ci-table">
  <thead><tr>{$headerCells}</tr></thead>
  <tbody>{$bodyRows}</tbody>
</table>
HTML;

        return <<<HTML
<details class="ci-node ci-table-node">
  <summary>{$label}<span class="ci-array">[{$meta['rowCount']} rows]</span></summary>
  <div class="ci-table-wrapper">
    {$tableHtml}
  </div>
</details>
HTML;
    }

    /**
     * Returns the CSS styles for the HTML dump.
     */
    private function css(): string
    {
        return <<<CSS
.ci-dump { font: 13px ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; line-height:1.4; }
.ci-line { padding:2px 0; padding-left: 1.0em; }
.ci-key { color: #545454; margin-right: 6px; }
.ci-varname { color: #A42323; font-weight: 600; margin-right: 6px; }
.ci-scalar { color: #2A843C; }
.ci-array { color: #1A76C7; }
.ci-object { color: #0C7E7E; }
.ci-resource { color: #882D9F; }
.ci-null { color: #535A5F; }
.ci-unknown { color: #757575; font-style: italic; }
.ci-method-args { color: #888888; opacity: 0.8; }
.ci-node > summary { cursor: pointer; padding: 2px 0; }
.ci-children { margin-left: 12px; border-left: 1px solid #eee; padding-left: 10px; }
.ci-image-node .ci-image-preview { margin: 10px 0; text-align: center; }
.ci-image-node .ci-image-preview img { max-width: 300px; max-height: 300px; border: 1px solid #ddd; border-radius: 4px; }
.ci-table-wrapper { margin: 0 12px; padding: 10px 0; }
.ci-table { border-collapse: collapse; width: auto; font-size: 12px; }
.ci-table th { background-color: #f0f0f0; padding: 6px 12px; text-align: left; font-weight: 600; border: 1px solid #ddd; }
.ci-table td { padding: 5px 10px; border: 1px solid #ddd; }
.ci-table-even { background-color: #fafafa; }
.ci-table-odd { background-color: #ffffff; }
.ci-table tr:hover { background-color: #f5f5f5; }
.ci-image-node > summary { padding: 4px 0; }
.ci-image-preview { display: block; margin: 8px 0; max-width: 400px; max-height: 400px; width: auto; height: auto; border: 1px solid #ddd; border-radius: 3px; background-color: #fafafa; padding: 4px; object-fit: contain; }
.ci-trace-file { color: #0066CC; font-weight: 500; }
.ci-trace-line { color: #888888; }
.ci-method-name { color: #0C7E7E; font-weight: 500; }
/* @media (prefers-color-scheme: dark) {
  .ci-dump { color: #e9ecef; }
  .ci-children { border-color: #ABABAB; }
  .ci-varname { color: #FF8F8F; }
  .ci-unknown { color: #B8A8A0; }
  .ci-method-args { color: #999999; }
  .ci-table th { background-color: #2d2d2d; color: #e9ecef; }
  .ci-table td { border-color: #444; }
  .ci-table-even { background-color: #1a1a1a; }
  .ci-table-odd { background-color: #252525; }
  .ci-table tr:hover { background-color: #3a3a3a; }
} */
CSS;
        }
}
