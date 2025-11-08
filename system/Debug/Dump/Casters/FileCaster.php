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
use CodeIgniter\Files\File;
use CodeIgniter\Images\Image;

/**
 * Custom caster for File objects.
 * Displays file path, size, mime type, and modification time.
 */
class FileCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle File objects (but not Image, which extends File)
        if (!$value instanceof File || $value instanceof Image) {
            return null;
        }

        return $this->castFile($value, $path, $depth, $dumper);
    }

    /**
     * Cast a File object.
     */
    private function castFile(File $file, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === PATH ===
        $realPath = $file->getRealPath();
        $displayPath = $realPath ?: (string) $file;
        $children['path'] = $dumper->normalize(
            $displayPath,
            "$path.path",
            $depth + 1,
            hideType: true
        );

        // === FILENAME ===
        $children['filename'] = $dumper->normalize(
            $file->getBasename(),
            "$path.filename",
            $depth + 1,
            hideType: true
        );

        // === SIZE ===
        try {
            $sizeBytes = $file->getSize();
            if ($sizeBytes !== false) {
                // Get human-readable size using binary units
                $sizeBinary = $file->getSizeByBinaryUnit();
                $children['size'] = $dumper->normalize(
                    "{$sizeBinary} ({$sizeBytes} bytes)",
                    "$path.size",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // File may not exist or be readable
        }

        // === MIME TYPE ===
        try {
            $mimeType = $file->getMimeType();
            $children['mimeType'] = $dumper->normalize(
                $mimeType,
                "$path.mimeType",
                $depth + 1,
                hideType: true
            );
        } catch (\Throwable) {
            // MIME type detection may fail
        }

        // === MODIFIED TIME ===
        try {
            $mtime = $file->getMTime();
            if ($mtime !== false) {
                $modifiedTime = date('Y-m-d H:i:s', $mtime);
                $children['modified'] = $dumper->normalize(
                    $modifiedTime,
                    "$path.modified",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // Modification time may not be available
        }

        // === PERMISSIONS ===
        try {
            $perms = $file->getPerms();
            if ($perms !== false) {
                $permString = substr(sprintf('%o', $perms), -4);
                $children['permissions'] = $dumper->normalize(
                    $permString,
                    "$path.permissions",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // Permissions may not be available
        }

        // === IS WRITABLE ===
        try {
            $isWritable = $file->isWritable();
            $children['writable'] = $dumper->normalize(
                $isWritable,
                "$path.writable",
                $depth + 1,
                hideType: true
            );
        } catch (\Throwable) {
            // Writability check may fail
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'File',
            summary: 'File: ' . $file->getBasename(),
            children: $children,
            meta: ['path' => $path]
        );
    }
}
