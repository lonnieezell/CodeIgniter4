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
use CodeIgniter\Files\FileCollection;

/**
 * Custom caster for FileCollection objects.
 * Displays a list of files in the collection with counts.
 */
class FileCollectionCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle FileCollection objects
        if (!$value instanceof FileCollection) {
            return null;
        }

        return $this->castFileCollection($value, $path, $depth, $dumper);
    }

    /**
     * Cast a FileCollection object.
     */
    private function castFileCollection(FileCollection $collection, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];

        // Get the files from the collection
        $files = $collection->get();
        $fileCount = count($files);

        if ($fileCount > 0) {
            // Create file paths array to display
            $filePaths = [];
            foreach ($files as $file) {
                if ($file instanceof \CodeIgniter\Files\File) {
                    $filePaths[] = $file->getRealPath() ?: (string) $file;
                } else {
                    $filePaths[] = (string) $file;
                }
            }

            // Add files as a table-like array if there are multiple files
            if ($fileCount > 1) {
                $children['files'] = $dumper->normalize(
                    $filePaths,
                    "$path.files",
                    $depth + 1
                );
            } else {
                // Single file - just show the path
                $children['files'] = $dumper->normalize(
                    $filePaths[0],
                    "$path.files",
                    $depth + 1,
                    hideType: true
                );
            }
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'FileCollection',
            summary: "FileCollection ({$fileCount} file" . ($fileCount !== 1 ? 's' : '') . ')',
            children: $children,
            meta: ['path' => $path]
        );
    }
}
