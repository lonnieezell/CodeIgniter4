<?php

declare(strict_types=1);

namespace CodeIgniter\Router;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Config\Modules;
use Config\Routing;

/**
 * Performance benchmark comparing FastRoute vs Legacy Router
 *
 * Tests three scenarios:
 * - Small: 50 routes
 * - Medium: 500 routes
 * - Large: 5000 routes
 *
 * For each scenario, measures:
 * - Static route matching (best case)
 * - Dynamic route matching (average case)
 * - Last route matching (worst case)
 */
class RouterPerformanceTest extends CIUnitTestCase
{
    private Router $router;
    private RouteCollection $collection;

    protected function setUp(): void
    {
        parent::setUp();
        Services::reset(true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Create a router with specified configuration
     */
    private function createRouter(bool $useFastRoute = false): Router
    {
        $routes = new Routing();
        $routes->useFastRoute = $useFastRoute;

        $this->collection = new RouteCollection(Services::locator(), new Modules(), new Routing());

        $request = Services::incomingrequest(new App(), false);

        return new Router($this->collection, $request, $routes);
    }

    /**
     * Generate routes for testing
     *
     * @param int $count Number of routes to generate
     * @return array Statistics about generated routes
     */
    private function generateRoutes(int $count): array
    {
        $staticCount = 0;
        $dynamicCount = 0;

        // First route - static (will be matched first in some tests)
        $this->collection->add('first-route', 'TestController::firstMethod');
        $staticCount++;

        // Generate mix of static and dynamic routes (60% dynamic, 40% static)
        for ($i = 1; $i < $count - 1; $i++) {
            if ($i % 5 === 0 || $i % 5 === 1) {
                // Static route
                $this->collection->add("static-route-{$i}", "Controller{$i}::method");
                $staticCount++;
            } else {
                // Dynamic route with placeholder
                $this->collection->add("dynamic-route-{$i}/(:num)", "Controller{$i}::show/\$1");
                $dynamicCount++;
            }
        }

        // Last route - dynamic (worst case for linear matching)
        $this->collection->add('last-route/(:num)/(:alpha)', 'TestController::lastMethod/$1/$2');
        $dynamicCount++;

        return [
            'total' => $count,
            'static' => $staticCount,
            'dynamic' => $dynamicCount,
        ];
    }

    /**
     * Benchmark a routing scenario
     *
     * @param bool $useFastRoute Whether to use FastRoute
     * @param int $routeCount Number of routes
     * @param string $uri URI to match
     * @param int $iterations Number of times to run the test
     * @return array{time: float, memory: int, rate: float}
     */
    private function benchmark(bool $useFastRoute, int $routeCount, string $uri, int $iterations = 10000): array
    {
        $this->router = $this->createRouter($useFastRoute);
        $stats = $this->generateRoutes($routeCount);

        // Warm up
        for ($i = 0; $i < 100; $i++) {
            try {
                $this->router->handle($uri);
            } catch (\Exception $e) {
                // Ignore exceptions during warmup
            }
        }

        // Clear any cached state
        gc_collect_cycles();

        // Measure
        $startMemory = memory_get_usage();
        $startTime = hrtime(true); // Use high-resolution timer

        for ($i = 0; $i < $iterations; $i++) {
            try {
                $this->router->handle($uri);
            } catch (\Exception $e) {
                // Continue even if route not found
            }
        }

        $endTime = hrtime(true);
        $endMemory = memory_get_usage();

        $totalTime = ($endTime - $startTime) / 1e9; // Convert nanoseconds to seconds
        $avgTime = $totalTime / $iterations;
        $requestsPerSecond = $iterations / $totalTime;
        $memoryUsed = $endMemory - $startMemory;

        return [
            'time' => $avgTime * 1000000, // Convert to microseconds
            'memory' => $memoryUsed,
            'rate' => $requestsPerSecond,
            'stats' => $stats,
        ];
    }

    /**
     * Format results for display
     */
    private function formatResults(string $scenario, string $caseType, array $legacy, array $fastRoute): string
    {
        $speedup = $legacy['time'] / $fastRoute['time'];
        $improvement = (($legacy['time'] - $fastRoute['time']) / $legacy['time']) * 100;

        $output = "\n";
        $output .= "=== {$scenario} - {$caseType} ===\n";
        $output .= sprintf("Routes: %d total (%d static, %d dynamic)\n",
            $legacy['stats']['total'],
            $legacy['stats']['static'],
            $legacy['stats']['dynamic']
        );
        $output .= "\n";
        $output .= sprintf("Legacy Router:  %.2f μs/req  (%s req/s)\n",
            $legacy['time'],
            number_format($legacy['rate'], 0)
        );
        $output .= sprintf("FastRoute:      %.2f μs/req  (%s req/s)\n",
            $fastRoute['time'],
            number_format($fastRoute['rate'], 0)
        );
        $output .= "\n";
        $output .= sprintf("Speedup:        %.2fx %s\n",
            abs($speedup),
            $speedup > 1 ? 'faster' : 'slower'
        );
        $output .= sprintf("Improvement:    %.1f%% %s\n",
            abs($improvement),
            $improvement > 0 ? 'faster' : 'slower'
        );
        $output .= "\n";

        return $output;
    }

    /**
     * Run a complete benchmark scenario
     */
    private function runScenario(string $name, int $routeCount, int $iterations = 10000): string
    {
        $results = "\n" . str_repeat("=", 70) . "\n";
        $results .= strtoupper($name) . " SCENARIO ({$routeCount} routes, {$iterations} iterations)\n";
        $results .= str_repeat("=", 70) . "\n";

        // Test 1: Static route (first in list) - Best case
        $legacyStatic = $this->benchmark(false, $routeCount, 'first-route', $iterations);
        $fastRouteStatic = $this->benchmark(true, $routeCount, 'first-route', $iterations);
        $results .= $this->formatResults($name, "Static Route (Best Case)", $legacyStatic, $fastRouteStatic);

        // Test 2: Dynamic route in middle - Average case
        $middleRoute = (int) ($routeCount / 2);
        $legacyMiddle = $this->benchmark(false, $routeCount, "dynamic-route-{$middleRoute}/123", $iterations);
        $fastRouteMiddle = $this->benchmark(true, $routeCount, "dynamic-route-{$middleRoute}/123", $iterations);
        $results .= $this->formatResults($name, "Dynamic Route (Average Case)", $legacyMiddle, $fastRouteMiddle);

        // Test 3: Last route - Worst case
        $legacyLast = $this->benchmark(false, $routeCount, 'last-route/999/test', $iterations);
        $fastRouteLast = $this->benchmark(true, $routeCount, 'last-route/999/test', $iterations);
        $results .= $this->formatResults($name, "Last Route (Worst Case)", $legacyLast, $fastRouteLast);

        return $results;
    }

    public function testPerformanceBenchmark(): void
    {
        $output = "\n\n";
        $output .= str_repeat("=", 70) . "\n";
        $output .= "FASTROUTE vs LEGACY ROUTER - PERFORMANCE BENCHMARK\n";
        $output .= str_repeat("=", 70) . "\n";
        $output .= "Testing routing performance across different scenarios\n";
        $output .= "Uses high-resolution timer (hrtime) for accurate measurements\n";
        $output .= str_repeat("=", 70) . "\n";

        // Small scenario: 50 routes, 10k iterations
        $output .= $this->runScenario("SMALL", 50, 10000);

        // Medium scenario: 500 routes, 5k iterations
        $output .= $this->runScenario("MEDIUM", 500, 5000);

        // Large scenario: 2000 routes, 1k iterations
        $output .= $this->runScenario("LARGE", 2000, 1000);

        // Summary
        $output .= "\n" . str_repeat("=", 70) . "\n";
        $output .= "SUMMARY\n";
        $output .= str_repeat("=", 70) . "\n";
        $output .= "FastRoute provides:\n";
        $output .= "- O(1) hash lookups for static routes\n";
        $output .= "- Chunked regex matching for dynamic routes\n";
        $output .= "- Performance benefits vary based on:\n";
        $output .= "  * Number of routes in application\n";
        $output .= "  * Position of matched route in route list\n";
        $output .= "  * Ratio of static vs dynamic routes\n";
        $output .= "\n";
        $output .= "Legacy router:\n";
        $output .= "- Linear O(n) matching through all routes\n";
        $output .= "- Performance degrades as route count increases\n";
        $output .= "- Worst case: matching last route requires checking all routes\n";
        $output .= "\n";

        // Write to file as well
        $logFile = WRITEPATH . 'logs/router_benchmark_' . date('Y-m-d_His') . '.txt';
        @mkdir(dirname($logFile), 0777, true);
        file_put_contents($logFile, $output);

        $output .= "Results saved to: {$logFile}\n";
        $output .= str_repeat("=", 70) . "\n\n";

        // Output results
        fwrite(STDOUT, $output);

        // Assert that we ran the benchmark successfully
        $this->assertTrue(true, 'Performance benchmark completed');
    }

    /**
     * Quick sanity check test - just verifies both routers work
     */
    public function testBothRoutersWork(): void
    {
        // Legacy router
        $this->router = $this->createRouter(false);
        $this->generateRoutes(10);
        $this->router->handle('first-route');
        $this->assertStringContainsString('TestController', $this->router->controllerName());

        // FastRoute
        $this->router = $this->createRouter(true);
        $this->generateRoutes(10);
        $this->router->handle('first-route');
        $this->assertStringContainsString('TestController', $this->router->controllerName());

        $this->assertTrue(true);
    }
}
