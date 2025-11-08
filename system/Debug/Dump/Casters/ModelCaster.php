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

use CodeIgniter\BaseModel;
use CodeIgniter\Debug\Dump\CasterInterface;
use CodeIgniter\Debug\Dump\Dumper;
use CodeIgniter\Debug\Dump\Node;
use CodeIgniter\Model;
use ReflectionClass;

/**
 * Custom caster for Model to show only the most relevant debugging info.
 */
class ModelCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle Model objects
        if (!$value instanceof Model) {
            return null;
        }

        return $this->castModel($value, $path, $depth, $dumper);
    }

    /**
     * Cast a Model object.
     */
    private function castModel(Model $model, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];
        $className = $model::class;

        // === CONFIGURATION ===
        $config = [];

        // Get protected properties via reflection
        $reflection = new ReflectionClass($model);

        // Table name
        if ($reflection->hasProperty('table')) {
            $prop = $reflection->getProperty('table');
            $prop->setAccessible(true);
            $table = $prop->getValue($model);
            if ($table) {
                $config['table'] = $table;
            }
        }

        // Primary key
        if ($reflection->hasProperty('primaryKey')) {
            $prop = $reflection->getProperty('primaryKey');
            $prop->setAccessible(true);
            $config['primaryKey'] = $prop->getValue($model);
        }

        // Return type
        if ($reflection->hasProperty('returnType')) {
            $prop = $reflection->getProperty('returnType');
            $prop->setAccessible(true);
            $config['returnType'] = $prop->getValue($model);
        }

        // Timestamps
        if ($reflection->hasProperty('useTimestamps')) {
            $prop = $reflection->getProperty('useTimestamps');
            $prop->setAccessible(true);
            if ($prop->getValue($model)) {
                $config['timestamps'] = 'enabled';

                if ($reflection->hasProperty('createdField')) {
                    $prop2 = $reflection->getProperty('createdField');
                    $prop2->setAccessible(true);
                    $config['createdField'] = $prop2->getValue($model);
                }

                if ($reflection->hasProperty('updatedField')) {
                    $prop2 = $reflection->getProperty('updatedField');
                    $prop2->setAccessible(true);
                    $config['updatedField'] = $prop2->getValue($model);
                }
            }
        }

        // Soft deletes
        if ($reflection->hasProperty('useSoftDeletes')) {
            $prop = $reflection->getProperty('useSoftDeletes');
            $prop->setAccessible(true);
            if ($prop->getValue($model)) {
                $config['softDeletes'] = 'enabled';

                if ($reflection->hasProperty('deletedField')) {
                    $prop2 = $reflection->getProperty('deletedField');
                    $prop2->setAccessible(true);
                    $config['deletedField'] = $prop2->getValue($model);
                }
            }
        }

        if (!empty($config)) {
            $children['config'] = $dumper->normalize($config, "$path.config", $depth + 1);
        }

        // === ALLOWED FIELDS ===
        if ($reflection->hasProperty('allowedFields')) {
            $prop = $reflection->getProperty('allowedFields');
            $prop->setAccessible(true);
            $allowed = $prop->getValue($model);

            if (!empty($allowed)) {
                $children['allowedFields'] = $dumper->normalize($allowed, "$path.allowedFields", $depth + 1);
            }
        }

        // === VALIDATION ===
        if ($reflection->hasProperty('validationRules')) {
            $prop = $reflection->getProperty('validationRules');
            $prop->setAccessible(true);
            $rules = $prop->getValue($model);

            if (!empty($rules)) {
                $children['validationRules'] = $dumper->normalize($rules, "$path.validationRules", $depth + 1);
            }
        }

        // === MODEL EVENTS ===
        $events = [];

        $eventProperties = [
            'beforeInsert' => 'beforeInsert',
            'afterInsert' => 'afterInsert',
            'beforeUpdate' => 'beforeUpdate',
            'afterUpdate' => 'afterUpdate',
            'beforeInsertBatch' => 'beforeInsertBatch',
            'afterInsertBatch' => 'afterInsertBatch',
            'beforeUpdateBatch' => 'beforeUpdateBatch',
            'afterUpdateBatch' => 'afterUpdateBatch',
            'beforeDelete' => 'beforeDelete',
            'afterDelete' => 'afterDelete',
        ];

        foreach ($eventProperties as $propName => $eventName) {
            if ($reflection->hasProperty($propName)) {
                $prop = $reflection->getProperty($propName);
                $prop->setAccessible(true);
                $callbacks = $prop->getValue($model);

                if (!empty($callbacks)) {
                    $eventCallbacks = [];
                    foreach ($callbacks as $callback) {
                        $callbackStr = '';
                        if (is_string($callback)) {
                            $callbackStr = $callback;
                        } elseif (is_array($callback) && count($callback) === 2) {
                            $callbackStr = is_string($callback[0])
                                ? "{$callback[0]}::{$callback[1]}"
                                : get_class($callback[0]) . "::{$callback[1]}";
                        } elseif ($callback instanceof \Closure) {
                            $callbackStr = 'Closure';
                        }

                        if ($callbackStr) {
                            $eventCallbacks[] = $callbackStr;
                        }
                    }

                    if (!empty($eventCallbacks)) {
                        $events[$eventName] = implode(', ', $eventCallbacks);
                    }
                }
            }
        }

        if (!empty($events)) {
            // Build event nodes manually so we can hide types for the event names
            $eventNodes = [];
            foreach ($events as $eventName => $eventCallbacks) {
                $eventNodes[$eventName] = $dumper->normalize(
                    $eventCallbacks,
                    "$path.events.{$eventName}",
                    $depth + 1,
                    hideType: true
                );
            }
            $children['events'] = new Node(
                kind: Node::KIND_ARRAY,
                type: 'array',
                summary: sprintf('array(%d)', count($eventNodes)),
                children: $eventNodes,
                meta: ['path' => "$path.events"]
            );
        }

        // === CUSTOM METHODS ===
        $customMethods = $this->getCustomMethods($reflection);
        if (!empty($customMethods)) {
            // Build method nodes with hideType for method signatures
            $methodNodes = [];
            foreach ($customMethods as $index => $methodName) {
                $method = $reflection->getMethod($methodName);
                $signature = $this->getMethodSignature($method);

                $methodNodes[$index] = $dumper->normalize(
                    $signature,
                    "$path.customMethods[{$index}]",
                    $depth + 1,
                    hideType: true
                );
            }
            $children['customMethods'] = new Node(
                kind: Node::KIND_ARRAY,
                type: 'array',
                summary: sprintf('array(%d)', count($methodNodes)),
                children: $methodNodes,
                meta: ['path' => "$path.customMethods"]
            );
        }

        // === CUSTOM PROPERTIES ===
        $customProperties = $this->getCustomProperties($reflection, $model, $dumper, $path, $depth);
        foreach ($customProperties as $propName => $propValue) {
            $children[$propName] = $dumper->normalize($propValue, "$path.$propName", $depth + 1);
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'Model',
            summary: "{$className}",
            children: $children,
            meta: ['path' => $path]
        );
    }

    /**
     * Get custom properties defined in the model (not framework properties).
     *
     * @return array<string, mixed>
     */
    private function getCustomProperties(ReflectionClass $reflection, Model $model, Dumper $dumper, string $path, int $depth): array
    {
        $customProperties = [];
        $baseModelClass = Model::class;
        $baseModelParentClass = BaseModel::class;

        // Get properties from Model and BaseModel classes
        $baseModelReflection = new ReflectionClass($baseModelClass);
        $baseModelParentReflection = new ReflectionClass($baseModelParentClass);

        $frameworkPropertyNames = [];
        foreach ($baseModelReflection->getProperties() as $prop) {
            $frameworkPropertyNames[] = $prop->getName();
        }
        foreach ($baseModelParentReflection->getProperties() as $prop) {
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

            // Skip properties declared in Model or BaseModel
            $declaringClass = $prop->getDeclaringClass()->getName();
            if ($declaringClass === $baseModelClass || $declaringClass === $baseModelParentClass) {
                continue;
            }

            // Get the property value
            $prop->setAccessible(true);
            $value = $prop->getValue($model);

            // Store the actual value, not a Node
            $customProperties[$propName] = $value;
        }

        // Sort alphabetically by key
        ksort($customProperties);

        return $customProperties;
    }

    /**
     * Get custom methods defined in the model (not inherited from Model or BaseModel).
     *
     * @return array<int, string>
     */
    private function getCustomMethods(ReflectionClass $reflection): array
    {
        $customMethods = [];
        $baseModelClass = Model::class;
        $baseModelParentClass = BaseModel::class;

        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $declaringClassName = $method->getDeclaringClass()->getName();

            // Skip methods inherited from Model or BaseModel
            if ($declaringClassName === $baseModelClass || $declaringClassName === $baseModelParentClass) {
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

    /**
     * Get method signature including parameters and return type.
     */
    private function getMethodSignature(\ReflectionMethod $method): string
    {
        $params = [];
        foreach ($method->getParameters() as $param) {
            $paramStr = '';

            // Add type hint
            if ($param->hasType()) {
                $type = $param->getType();
                if ($type instanceof \ReflectionUnionType) {
                    $paramStr .= implode('|', array_map(static fn ($t) => $t->getName(), $type->getTypes())) . ' ';
                } elseif ($type instanceof \ReflectionNamedType) {
                    $paramStr .= ($type->allowsNull() ? '?' : '') . $type->getName() . ' ';
                }
            }

            // Add parameter name
            $paramStr .= '$' . $param->getName();

            // Add default value if available
            if ($param->isDefaultValueAvailable()) {
                $defaultValue = $param->getDefaultValue();
                if (is_string($defaultValue)) {
                    $paramStr .= " = '" . $defaultValue . "'";
                } elseif (is_bool($defaultValue)) {
                    $paramStr .= $defaultValue ? ' = true' : ' = false';
                } elseif (is_null($defaultValue)) {
                    $paramStr .= ' = null';
                } elseif (is_array($defaultValue)) {
                    $paramStr .= ' = []';
                } else {
                    $paramStr .= ' = ' . $defaultValue;
                }
            } elseif ($param->isVariadic()) {
                $paramStr = '...' . $paramStr;
            }

            $params[] = $paramStr;
        }

        // Build signature with HTML markup for visual separation
        $methodName = htmlspecialchars($method->getName(), ENT_QUOTES, 'UTF-8');
        $argsStr = htmlspecialchars(implode(', ', $params), ENT_QUOTES, 'UTF-8');
        $signature = "<span class='ci-method-name'>{$methodName}</span><span class='ci-method-args'>({$argsStr})</span>";

        // Add return type
        if ($method->hasReturnType()) {
            $returnType = $method->getReturnType();
            if ($returnType instanceof \ReflectionUnionType) {
                $returnTypeStr = implode('|', array_map(static fn ($t) => $t->getName(), $returnType->getTypes()));
            } elseif ($returnType instanceof \ReflectionNamedType) {
                $returnTypeStr = ($returnType->allowsNull() ? '?' : '') . $returnType->getName();
            }
            $signature .= "<span class='ci-method-args'>: {$returnTypeStr}</span>";
        }

        return $signature;
    }
}
