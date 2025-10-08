# Router Performance Benchmark

## Quick Start

Run the performance benchmark:

```bash
vendor/bin/phpunit --filter testPerformanceBenchmark tests/system/Router/RouterPerformanceTest.php
```

Results will be displayed in the terminal and saved to `writable/logs/router_benchmark_YYYY-MM-DD_HHMMSS.txt`

## What It Tests

The benchmark compares **FastRoute vs Legacy Router** across three scenarios:

### Scenarios

1. **Small** (50 routes, 10,000 iterations)
2. **Medium** (500 routes, 5,000 iterations)
3. **Large** (2,000 routes, 1,000 iterations)

### Test Cases

For each scenario, three cases are tested:

- **Best Case**: First static route (minimal overhead)
- **Average Case**: Dynamic route in middle of list
- **Worst Case**: Last route (maximum overhead for linear search)

## Route Distribution

Routes are generated with a realistic mix:
- **40% Static Routes**: e.g., `/users`, `/posts`
- **60% Dynamic Routes**: e.g., `/posts/(:num)`, `/users/(:alpha)/edit`

## Understanding the Results

### Metrics Reported

- **Time per request**: Microseconds (μs) - lower is better
- **Requests per second**: Throughput - higher is better
- **Speedup**: FastRoute time vs Legacy Router time
- **Improvement**: Percentage faster/slower

### Sample Output

```
=== SMALL - Last Route (Worst Case) ===
Routes: 50 total (20 static, 30 dynamic)

Legacy Router:  18.15 μs/req  (55,105 req/s)
FastRoute:      15.86 μs/req  (63,065 req/s)

Speedup:        1.14x faster
Improvement:    12.6% faster
```

### Interpreting Results

- **Within 1-2%**: Statistically equivalent (system noise)
- **3-5% difference**: Slight advantage
- **10%+ difference**: Meaningful improvement
- **Results vary by run**: CPU load, system state, etc.

## What We Learned

Performance testing revealed that FastRoute and the legacy router perform **nearly identically** in real-world scenarios because:

1. **Route matching is fast** - Even O(n) linear search is quick for thousands of routes
2. **Framework overhead dominates** - Controller resolution, parameter extraction, etc. take more time
3. **Modern CPUs are optimized** - Branch prediction and caching minimize loop overhead

### When FastRoute Helps

FastRoute provides benefits in:

- **Very large apps** (1000+ routes)
- **High-traffic scenarios** (millions of requests/day)
- **Worst-case scenarios** (matching last route in list)
- **Future-proofing** as your application grows

## Running Individual Tests

Run just the sanity check (quick validation):

```bash
vendor/bin/phpunit --filter testBothRoutersWork tests/system/Router/RouterPerformanceTest.php
```

Run all performance tests:

```bash
vendor/bin/phpunit tests/system/Router/RouterPerformanceTest.php
```

## Customizing the Benchmark

Edit `RouterPerformanceTest.php` to adjust:

- **Route counts**: Change scenario sizes (line 217-222)
- **Iterations**: Increase/decrease for more/less precision
- **Route ratios**: Modify static/dynamic distribution (line 70-81)
- **Chunk size**: Test different FastRoute chunk sizes

## See Also

- `FASTROUTE_BENCHMARK_RESULTS.md` - Detailed analysis and recommendations
- `FASTROUTE_IMPLEMENTATION_PLAN.md` - Implementation details
- `tests/system/Router/FastRouteTest.php` - Unit tests (21 tests)
- `tests/system/Router/RouterTest.php` - Integration tests (70 tests including 9 FastRoute tests)

---

**Total Test Coverage**: 93 tests (91 functional + 2 performance)
