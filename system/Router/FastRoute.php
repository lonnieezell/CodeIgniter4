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

namespace CodeIgniter\Router;

use Closure;

/**
 * FastRoute - Optimized route matching using static lookups and chunked regex patterns
 *
 * Based on Nikita Popov's FastRoute algorithm:
 * - Static routes use O(1) hash lookup
 * - Dynamic routes are grouped into chunks (~10 routes per regex)
 * - Uses PCRE's (*MARK) feature to identify matched routes
 *
 * @see https://www.npopov.com/2014/02/18/Fast-request-routing-using-regular-expressions.html
 */
class FastRoute
{
    /**
     * Static routes grouped by HTTP method (O(1) lookup)
     *
     * @var array<string, array<string, string>>
     */
    private array $staticRoutes = [];

    /**
     * Dynamic routes grouped by HTTP method and chunks
     *
     * @var array<string, array{chunks: array}>
     */
    private array $dynamicRoutes = [];

    /**
     * Number of routes per regex chunk
     */
    private int $chunkSize;

    /**
     * RouteCollection reference
     */
    private RouteCollectionInterface $collection;

    /**
     * Compile routes for fast matching
     *
     * @param RouteCollectionInterface $collection The route collection
     * @param int                      $chunkSize  Number of routes per regex chunk
     */
    public function __construct(RouteCollectionInterface $collection, int $chunkSize = 10)
    {
        $this->collection = $collection;
        $this->chunkSize  = $chunkSize;
        $this->compileRoutes();
    }

    /**
     * Match URI against compiled routes
     *
     * @return array{handler: string|Closure, params: array, route: string, originalRoute: string}|null
     */
    public function match(string $uri, string $httpMethod = 'GET'): ?array
    {
        // Normalize URI
        $uri = $uri === '/' ? $uri : trim($uri, '/ ');

        // Try static routes first (O(1) hash lookup)
        if (isset($this->staticRoutes[$httpMethod][$uri])) {
            return [
                'handler'       => $this->staticRoutes[$httpMethod][$uri],
                'params'        => [],
                'route'         => $uri,
                'originalRoute' => $uri,
            ];
        }

        // Try dynamic routes (chunked regex)
        if (isset($this->dynamicRoutes[$httpMethod])) {
            return $this->matchDynamicRoute($uri, $httpMethod);
        }

        return null;
    }

    /**
     * Compile routes into static and dynamic structures
     */
    private function compileRoutes(): void
    {
        foreach (Router::HTTP_METHODS as $httpMethod) {
            $routes = $this->collection->getRoutes($httpMethod);

            if ($routes === []) {
                continue;
            }

            $static  = [];
            $dynamic = [];

            foreach ($routes as $route => $handler) {
                $originalRoute = (string) $route;

                // Convert {locale} placeholder to regex pattern, just like legacy Router does
                if (str_contains($originalRoute, '{locale}')) {
                    $route = str_replace('{locale}', '[^/]+', $originalRoute);
                }

                if ($this->isStaticRoute($route)) {
                    $static[$route] = $handler;
                } else {
                    $dynamic[] = [
                        'route'         => $route,
                        'handler'       => $handler,
                        'originalRoute' => $originalRoute,
                    ];
                }
            }

            // Store static routes
            if ($static !== []) {
                $this->staticRoutes[$httpMethod] = $static;
            }

            // Compile and store dynamic routes
            if ($dynamic !== []) {
                $this->dynamicRoutes[$httpMethod] = [
                    'chunks' => $this->compileChunks($dynamic),
                ];
            }
        }
    }

    /**
     * Check if route is static (no regex patterns)
     *
     * Note: Routes from RouteCollection already have placeholders
     * converted to regex patterns, so we check for regex special chars.
     */
    private function isStaticRoute(string $route): bool
    {
        return ! str_contains($route, '(')
            && ! str_contains($route, '[')
            && ! str_contains($route, '{');
    }

    /**
     * Compile dynamic routes into chunks
     *
     * @param array $routes List of dynamic routes
     *
     * @return array List of compiled chunks
     */
    private function compileChunks(array $routes): array
    {
        $chunks      = [];
        $routeChunks = array_chunk($routes, $this->chunkSize);

        foreach ($routeChunks as $chunk) {
            $chunks[] = $this->compileChunk($chunk);
        }

        return $chunks;
    }

    /**
     * Compile a single chunk into a combined regex pattern
     *
     * Uses branch reset (?| ) to normalize capture group numbers and
     * (*MARK:name) to identify which route matched.
     */
    private function compileChunk(array $routes): array
    {
        $patterns = [];
        $routeMap = [];

        foreach ($routes as $index => $routeData) {
            // Routes already have regex patterns from RouteCollection
            // Just wrap them for branch reset and add MARK
            $pattern    = $routeData['route'];
            $patterns[] = "(?:{$pattern})(*MARK:{$index})";
            $routeMap[$index] = $routeData;
        }

        // Combine patterns using branch reset (?| to normalize capture groups
        $regex = '~^(?|' . implode('|', $patterns) . ')$~u';

        return [
            'regex'  => $regex,
            'routes' => $routeMap,
        ];
    }

    /**
     * Match URI against dynamic route chunks
     *
     * @return array{handler: string, params: array, route: string}|null
     */
    private function matchDynamicRoute(string $uri, string $httpMethod): ?array
    {
        foreach ($this->dynamicRoutes[$httpMethod]['chunks'] as $chunk) {
            if (preg_match($chunk['regex'], $uri, $matches)) {
                // Get route index from MARK
                $mark      = $matches['MARK'];
                $routeData = $chunk['routes'][$mark];

                // Extract parameters (remove full match and MARK)
                array_shift($matches);
                unset($matches['MARK']);

                // Get only numeric-keyed entries (the captured parameters)
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_int($key)) {
                        $params[] = $value;
                    }
                }

                return [
                    'handler'       => $routeData['handler'],
                    'params'        => $params,
                    'route'         => $routeData['route'],
                    'originalRoute' => $routeData['originalRoute'] ?? $routeData['route'],
                ];
            }
        }

        return null;
    }
}
