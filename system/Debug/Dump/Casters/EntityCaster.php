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
use CodeIgniter\Entity\Entity;
use ReflectionClass;

/**
 * Custom caster for Entity to show only the most relevant debugging info.
 */
class EntityCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Entity objects
        if (!$value instanceof Entity) {
            return null;
        }

        return $this->castEntity($value, $path, $depth, $dumper);
    }

    /**
     * Cast an Entity object.
     */
    private function castEntity(Entity $entity, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];
        $className = $entity::class;

        // === ATTRIBUTES ===
        $reflection = new ReflectionClass($entity);

        if ($reflection->hasProperty('attributes')) {
            $prop = $reflection->getProperty('attributes');
            $prop->setAccessible(true);
            $attributes = $prop->getValue($entity);

            if (!empty($attributes)) {
                // Filter out internal attributes (starting with _)
                $filtered = array_filter(
                    $attributes,
                    static fn ($key) => !str_starts_with($key, '_'),
                    ARRAY_FILTER_USE_KEY
                );

                if (!empty($filtered)) {
                    $children['attributes'] = $dumper->normalize($filtered, "$path.attributes", $depth + 1);
                }
            }
        }

        // === CHANGES ===
        if ($reflection->hasProperty('original')) {
            $prop = $reflection->getProperty('original');
            $prop->setAccessible(true);
            $original = $prop->getValue($entity);

            if ($reflection->hasProperty('attributes')) {
                $prop2 = $reflection->getProperty('attributes');
                $prop2->setAccessible(true);
                $current = $prop2->getValue($entity);

                // Compare for changes and build change nodes manually
                $changeNodes = [];
                foreach ($current as $key => $value) {
                    if (!str_starts_with($key, '_') && (!array_key_exists($key, $original) || $original[$key] !== $value)) {
                        $originalValue = $original[$key] ?? '<not set>';

                        // Create change entry with custom nodes
                        $changeEntry = [];

                        // Original value node - hide type if it's '<not set>'
                        $hideOriginalType = ($originalValue === '<not set>');
                        $changeEntry['original'] = $dumper->normalize(
                            $originalValue,
                            "$path.changes.{$key}.original",
                            $depth + 2,
                            hideType: $hideOriginalType
                        );

                        // Current value node
                        $changeEntry['current'] = $dumper->normalize($value, "$path.changes.{$key}.current", $depth + 2);

                        $changeNodes[$key] = new Node(
                            kind: Node::KIND_ARRAY,
                            type: 'array',
                            summary: 'array(2)',
                            children: $changeEntry,
                            meta: ['path' => "$path.changes.{$key}"]
                        );
                    }
                }

                if (!empty($changeNodes)) {
                    $children['changes'] = new Node(
                        kind: Node::KIND_ARRAY,
                        type: 'array',
                        summary: sprintf('array(%d)', count($changeNodes)),
                        children: $changeNodes,
                        meta: ['path' => "$path.changes"]
                    );
                }
            }
        }

        // === CONFIGURATION ===
        // Datamap
        if ($reflection->hasProperty('datamap')) {
            $prop = $reflection->getProperty('datamap');
            $prop->setAccessible(true);
            $datamap = $prop->getValue($entity);

            if (!empty($datamap)) {
                $children['datamap'] = $dumper->normalize($datamap, "$path.datamap", $depth + 1);
            }
        }

        // Dates
        if ($reflection->hasProperty('dates')) {
            $prop = $reflection->getProperty('dates');
            $prop->setAccessible(true);
            $dates = $prop->getValue($entity);

            if (!empty($dates)) {
                $children['dates'] = $dumper->normalize($dates, "$path.dates", $depth + 1);
            }
        }

        // Casts
        if ($reflection->hasProperty('casts')) {
            $prop = $reflection->getProperty('casts');
            $prop->setAccessible(true);
            $casts = $prop->getValue($entity);

            if (!empty($casts)) {
                $children['casts'] = $dumper->normalize($casts, "$path.casts", $depth + 1);
            }
        }

        // Custom cast handlers
        if ($reflection->hasProperty('castHandlers')) {
            $prop = $reflection->getProperty('castHandlers');
            $prop->setAccessible(true);
            $castHandlers = $prop->getValue($entity);

            if (!empty($castHandlers)) {
                $children['castHandlers'] = $dumper->normalize($castHandlers, "$path.castHandlers", $depth + 1);
            }
        }

        // === CUSTOM METHODS ===
        $customMethods = $this->getCustomMethods($reflection);
        if (!empty($customMethods)) {
            $children['customMethods'] = $dumper->normalize($customMethods, "$path.customMethods", $depth + 1);
        }

        // === CUSTOM PROPERTIES ===
        $customProperties = $this->getCustomProperties($reflection, $entity, $dumper, $path, $depth);
        foreach ($customProperties as $propName => $propValue) {
            $children[$propName] = $dumper->normalize($propValue, "$path.$propName", $depth + 1);
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Entity',
            summary: "{$className}",
            children: $children,
            meta: ['path' => $path]
        );
    }

    /**
     * Get custom properties defined in the entity (not framework properties).
     *
     * @return array<string, mixed>
     */
    private function getCustomProperties(ReflectionClass $reflection, Entity $entity, Dumper $dumper, string $path, int $depth): array
    {
        $customProperties = [];
        $baseEntityClass = Entity::class;

        // Get properties from Entity base class
        $baseEntityReflection = new ReflectionClass($baseEntityClass);

        $frameworkPropertyNames = [];
        foreach ($baseEntityReflection->getProperties() as $prop) {
            $frameworkPropertyNames[] = $prop->getName();
        }

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PUBLIC) as $prop) {
            $propName = $prop->getName();

            // Skip framework properties
            if (in_array($propName, $frameworkPropertyNames, true)) {
                continue;
            }

            // Skip properties starting with underscore (typically internal)
            if (str_starts_with($propName, '_')) {
                continue;
            }

            // Skip properties declared in Entity base class
            $declaringClass = $prop->getDeclaringClass()->getName();
            if ($declaringClass === $baseEntityClass) {
                continue;
            }

            // Get the property value
            $prop->setAccessible(true);
            $value = $prop->getValue($entity);

            // Store the actual value, not a Node
            $customProperties[$propName] = $value;
        }

        // Sort alphabetically by key
        ksort($customProperties);

        return $customProperties;
    }

    /**
     * Get custom methods defined in the entity (not inherited from Entity).
     *
     * @return array<int, string>
     */
    private function getCustomMethods(ReflectionClass $reflection): array
    {
        $customMethods = [];
        $baseEntityClass = Entity::class;

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $declaringClassName = $method->getDeclaringClass()->getName();

            // Skip methods inherited from Entity
            if ($declaringClassName === $baseEntityClass) {
                continue;
            }

            // Skip magic methods
            if (str_starts_with($method->getName(), '__')) {
                continue;
            }

            // Add custom public method
            $customMethods[] = $method->getName();
        }

        return $customMethods;
    }
}
