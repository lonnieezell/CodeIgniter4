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
use CodeIgniter\Images\Image;

/**
 * Custom caster for Image objects.
 * Displays image path, dimensions, type, and other image-specific information.
 */
class ImageCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Image objects
        if (!$value instanceof Image) {
            return null;
        }

        return $this->castImage($value, $path, $depth, $dumper);
    }

    /**
     * Cast an Image object.
     */
    private function castImage(Image $image, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // === PATH ===
        $realPath = $image->getRealPath();
        $displayPath = $realPath ?: (string) $image;
        $children['path'] = $dumper->normalize(
            $displayPath,
            "$path.path",
            $depth + 1,
            hideType: true
        );

        // === FILENAME ===
        $children['filename'] = $dumper->normalize(
            $image->getBasename(),
            "$path.filename",
            $depth + 1,
            hideType: true
        );

        // === SIZE (file size) ===
        try {
            $sizeBytes = $image->getSize();
            if ($sizeBytes !== false) {
                $sizeBinary = $image->getSizeByBinaryUnit();
                $children['fileSize'] = $dumper->normalize(
                    "{$sizeBinary} ({$sizeBytes} bytes)",
                    "$path.fileSize",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // File size may not be available
        }

        // === IMAGE DIMENSIONS ===
        try {
            // Try to get image properties if not already loaded
            if (empty($image->origWidth) || empty($image->origHeight)) {
                try {
                    $image->getProperties();
                } catch (\Throwable) {
                    // Image properties may not be readable
                }
            }

            if (!empty($image->origWidth) && !empty($image->origHeight)) {
                $dimensions = "{$image->origWidth}×{$image->origHeight}";
                $children['dimensions'] = $dumper->normalize(
                    $dimensions,
                    "$path.dimensions",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // Dimensions may not be available
        }

        // === IMAGE TYPE ===
        try {
            if (!empty($image->imageType)) {
                $types = [
                    IMAGETYPE_GIF  => 'GIF',
                    IMAGETYPE_JPEG => 'JPEG',
                    IMAGETYPE_PNG  => 'PNG',
                    IMAGETYPE_WEBP => 'WebP',
                ];
                $typeName = $types[$image->imageType] ?? 'Unknown';
                $children['imageType'] = $dumper->normalize(
                    $typeName,
                    "$path.imageType",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // Image type may not be available
        }

        // === MIME TYPE ===
        try {
            $mimeType = $image->getMimeType();
            if (!empty($image->mime)) {
                $children['mimeType'] = $dumper->normalize(
                    $image->mime,
                    "$path.mimeType",
                    $depth + 1,
                    hideType: true
                );
            } else {
                $children['mimeType'] = $dumper->normalize(
                    $mimeType,
                    "$path.mimeType",
                    $depth + 1,
                    hideType: true
                );
            }
        } catch (\Throwable) {
            // MIME type detection may fail
        }

        // === MODIFIED TIME ===
        try {
            $mtime = $image->getMTime();
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
            $perms = $image->getPerms();
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

        // === IMAGE PREVIEW ===
        $imageUrl = null;
        try {
            $imagePath = $image->getRealPath() ?: (string) $image;
            // For local files, use file path; for URLs, use the URL directly
            if (!empty($imagePath)) {
                $imageUrl = $imagePath;
            }
        } catch (\Throwable) {
            // Image URL may not be available
        }

        $meta = ['path' => $path];
        if ($imageUrl !== null) {
            $meta['imageUrl'] = $imageUrl;
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Image',
            summary: 'Image: ' . $image->getBasename(),
            children: $children,
            meta: $meta
        );
    }
}
