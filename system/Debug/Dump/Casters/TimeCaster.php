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
use CodeIgniter\I18n\Time;

/**
 * Custom caster for Time to show only the most relevant debugging info.
 */
class TimeCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Time objects
        if (!$value instanceof Time) {
            return null;
        }

        return $this->castTime($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Time object.
     */
    private function castTime(Time $time, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === FORMATTED DATETIME ===
        // Show the human-readable formatted datetime
        $children['formatted'] = $dumper->normalize(
            (string) $time,
            "$path.formatted",
            $depth + 1
        );

        // === TIMESTAMP ===
        // Show Unix timestamp
        $children['timestamp'] = $dumper->normalize(
            $time->getTimestamp(),
            "$path.timestamp",
            $depth + 1
        );

        // === TIMEZONE ===
        // Show timezone name (e.g., UTC, America/New_York) - hide type, just show the value
        $children['timezone'] = $dumper->normalize(
            $time->getTimezoneName(),
            "$path.timezone",
            $depth + 1,
            hideType: true
        );

        // === LOCALE ===
        // Show the locale (e.g., en-US) - hide type, just show the value
        $locale = $time->locale;
        if ($locale) {
            $children['locale'] = $dumper->normalize(
                $locale,
                "$path.locale",
                $depth + 1,
                hideType: true
            );
        }

        // === TIMEZONE OFFSET ===
        // Show the UTC offset for the current timezone - hide type, just show the value
        $offset = $time->getOffset();
        $offsetHours = $offset / 3600;
        $offsetStr = sprintf('%+d:%02d', (int) $offsetHours, abs($offset % 3600) / 60);
        $children['utcOffset'] = $dumper->normalize(
            $offsetStr,
            "$path.utcOffset",
            $depth + 1,
            hideType: true
        );

        // === DST ===
        // Show if daylight saving time is active
        $children['isDST'] = $dumper->normalize(
            $time->getDst(),
            "$path.isDST",
            $depth + 1
        );

        // === COMPONENTS ===
        $components = [
            'year' => $time->getYear(),
            'month' => $time->getMonth(),
            'day' => $time->getDay(),
            'hour' => $time->getHour(),
            'minute' => $time->getMinute(),
            'second' => $time->getSecond(),
            'dayOfWeek' => $time->getDayOfWeek(),
            'dayOfYear' => $time->getDayOfYear(),
            'weekOfYear' => $time->getWeekOfYear(),
            'quarter' => $time->getQuarter(),
        ];

        $children['components'] = $dumper->normalize(
            $components,
            "$path.components",
            $depth + 1
        );

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Time',
            summary: (string) $time,
            children: $children,
            meta: ['path' => $path]
        );
    }
}
