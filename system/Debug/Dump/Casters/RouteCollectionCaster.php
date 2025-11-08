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
use CodeIgniter\Router\RouteCollection;

/**
 * Custom caster for RouteCollection instances.
 * Displays registered routes, HTTP methods, and configuration.
 */
class RouteCollectionCaster implements CasterInterface
{
    public function cast(mixed $value, string $path, int $depth, Dumper $dumper): ?Node
    {
        // Only handle RouteCollection objects
        if (!$value instanceof RouteCollection) {
            return null;
        }

        return $this->castRouteCollection($value, $path, $depth, $dumper);
    }

    /**
     * Cast a RouteCollection object.
     */
    private function castRouteCollection(RouteCollection $routes, string $path, int $depth, Dumper $dumper): Node
    {
        $children = [];
        $allRoutes = [];

        // Default settings
        try {
            $children['defaultNamespace'] = $dumper->normalize(
                $routes->getDefaultNamespace(),
                "$path.defaultNamespace",
                $depth + 1
            );

            $children['defaultController'] = $dumper->normalize(
                $routes->getDefaultController(),
                "$path.defaultController",
                $depth + 1
            );

            $children['defaultMethod'] = $dumper->normalize(
                $routes->getDefaultMethod(),
                "$path.defaultMethod",
                $depth + 1
            );

            // Show autoRoute setting
            $children['autoRoute'] = $dumper->normalize(
                $routes->shouldAutoRoute(),
                "$path.autoRoute",
                $depth + 1
            );

            // Use reflection to access protected $routes property
            $reflection = new \ReflectionClass($routes);
            $routesProperty = $reflection->getProperty('routes');
            $routesProperty->setAccessible(true);
            $rawRoutes = $routesProperty->getValue($routes);

            // Get all routes organized by HTTP verb
            $allRoutes = $routes->getRoutes(includeWildcard: true);
            $routesByVerb = [];

            // Organize routes by verb for better display
            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', '*'] as $verb) {
                $verbRoutes = $routes->getRoutes($verb, includeWildcard: false);
                if (!empty($verbRoutes)) {
                    $verbDisplay = $verb === '*' ? 'ANY' : $verb;
                    $routesByVerb[$verbDisplay] = [];

                    // Get detailed route info from raw routes
                    if (isset($rawRoutes[$verb])) {
                        foreach ($rawRoutes[$verb] as $routeKey => $routeData) {
                            $from = $routeData['from'] ?? $routeKey;
                            $handler = $routeData['handler'] ?? '';

                            // Format handler for display
                            $handlerStr = is_string($handler) ? $handler : (is_array($handler) ? implode(' -> ', array_keys($handler)) : json_encode($handler));

                            $routesByVerb[$verbDisplay][] = [
                                'from' => $from,
                                'to' => $handlerStr
                            ];
                        }
                    }
                }
            }

            // Add routes section with detailed mappings
            if (!empty($routesByVerb)) {
                $routesChildren = [];
                foreach ($routesByVerb as $verb => $routes) {
                    $routeList = [];
                    foreach ($routes as $idx => $routeInfo) {
                        // Create colored summary with spans
                        $fromEscaped = htmlspecialchars($routeInfo['from'], ENT_QUOTES, 'UTF-8');
                        $toEscaped = htmlspecialchars($routeInfo['to'], ENT_QUOTES, 'UTF-8');
                        $summary = "<span class='ci-method-name'>{$fromEscaped}</span> → <span class='ci-method-args'>{$toEscaped}</span>";

                        $routeList[] = new Node(
                            kind: Node::KIND_SCALAR,
                            type: 'string',
                            summary: $summary,
                            hideType: true
                        );
                    }
                    $routesChildren[$verb] = new Node(
                        kind: Node::KIND_ARRAY,
                        type: 'array',
                        summary: 'array(' . count($routeList) . ')',
                        children: $routeList,
                        hideType: true
                    );
                }

                $children['routes'] = new Node(
                    kind: Node::KIND_OBJECT,
                    type: 'Routes',
                    summary: 'Routes: ' . count($routesByVerb) . ' HTTP method' . (count($routesByVerb) !== 1 ? 's' : ''),
                    children: $routesChildren,
                    hideType: true
                );
            } else {
                $children['routes'] = new Node(
                    kind: Node::KIND_SCALAR,
                    type: 'string',
                    summary: "string(0) ''",
                    hideType: true
                );
            }

            // Get placeholders
            $placeholders = $routes->getPlaceholders();
            if (!empty($placeholders)) {
                $placeholderChildren = [];
                foreach ($placeholders as $name => $pattern) {
                    $placeholderChildren[$name] = $dumper->normalize(
                        $pattern,
                        "$path.placeholders[$name]",
                        $depth + 1
                    );
                }
                $children['placeholders'] = new Node(
                    kind: Node::KIND_ARRAY,
                    type: 'array',
                    summary: 'array(' . count($placeholderChildren) . ')',
                    children: $placeholderChildren,
                    hideType: true
                );
            }
        } catch (\Throwable $e) {
            $children['_error'] = new Node(
                kind: Node::KIND_SCALAR,
                type: 'string',
                summary: "Error: " . $e->getMessage(),
                hideType: true
            );
        }

        return new Node(
            kind: Node::KIND_OBJECT,
            type: 'RouteCollection',
            summary: 'RouteCollection: ' . count($allRoutes) . ' route' . (count($allRoutes) !== 1 ? 's' : ''),
            children: $children,
            hideType: true
        );
    }
}
