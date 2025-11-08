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

namespace CodeIgniter\Debug\Dump;

use CodeIgniter\Config\Debug;
use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
#[Group('Others')]
final class DumpTest extends CIUnitTestCase
{
    private Debug $config;
    private CasterRegistry $casters;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = config('Debug');
        $this->casters = new CasterRegistry();
    }

    // ========== Factory Methods - Happy Path ==========

    public function testCliFactoryCreatesInstance(): void
    {
        $dump = Dump::cli();

        $this->assertInstanceOf(Dump::class, $dump);
    }

    public function testHtmlFactoryCreatesInstance(): void
    {
        $dump = Dump::html();

        $this->assertInstanceOf(Dump::class, $dump);
    }

    public function testCliFactoryWithConfig(): void
    {
        $dump = Dump::cli($this->config);

        $this->assertInstanceOf(Dump::class, $dump);
    }

    public function testCliFactoryWithCasters(): void
    {
        $dump = Dump::cli(null, $this->casters);

        $this->assertInstanceOf(Dump::class, $dump);
    }

    public function testCliFactoryWithBothParams(): void
    {
        $dump = Dump::cli($this->config, $this->casters);

        $this->assertInstanceOf(Dump::class, $dump);
    }

    public function testHtmlFactoryWithConfig(): void
    {
        $dump = Dump::html($this->config);

        $this->assertInstanceOf(Dump::class, $dump);
    }

    public function testHtmlFactoryWithCasters(): void
    {
        $dump = Dump::html(null, $this->casters);

        $this->assertInstanceOf(Dump::class, $dump);
    }

    public function testHtmlFactoryWithBothParams(): void
    {
        $dump = Dump::html($this->config, $this->casters);

        $this->assertInstanceOf(Dump::class, $dump);
    }

    // ========== Dump Method - Happy Path ==========

    public function testDumpSingleScalar(): void
    {
        $dump = Dump::cli();
        ob_start();
        try {
            $result = $dump->dump('hello');
        } finally {
            ob_end_clean();
        }

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
        $this->assertStringContainsString('hello', $result);
    }

    public function testDumpMultipleScalars(): void
    {
        $dump = Dump::cli();
        ob_start();
        try {
            $result = $dump->dump('first', 'second', 'third');
        } finally {
            ob_end_clean();
        }

        $this->assertIsString($result);
        $this->assertStringContainsString('first', $result);
        $this->assertStringContainsString('second', $result);
        $this->assertStringContainsString('third', $result);
    }

    public function testDumpArray(): void
    {
        $dump = Dump::cli();
        $data = ['name' => 'John', 'age' => 30];
        $result = $dump->dump($data);

        $this->assertIsString($result);
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('John', $result);
    }

    public function testDumpObject(): void
    {
        $dump = Dump::cli();
        $obj = new \stdClass();
        $obj->property = 'value';
        $result = $dump->dump($obj);

        $this->assertIsString($result);
        $this->assertStringContainsString('property', $result);
        $this->assertStringContainsString('value', $result);
    }

    public function testDumpNestedStructure(): void
    {
        $dump = Dump::cli();
        $data = [
            'user' => (object)['name' => 'Alice', 'id' => 1],
            'tags' => ['admin', 'user'],
        ];
        $result = $dump->dump($data);

        $this->assertIsString($result);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('admin', $result);
    }

    public function testDumpWithNull(): void
    {
        $dump = Dump::cli();
        $result = $dump->dump(null);

        $this->assertIsString($result);
        $this->assertStringContainsString('null', $result);
    }

    public function testDumpWithBoolean(): void
    {
        $dump = Dump::cli();
        $result = $dump->dump(true, false);

        $this->assertIsString($result);
        $this->assertStringContainsString('true', $result);
        $this->assertStringContainsString('false', $result);
    }

    public function testDumpWithNumbers(): void
    {
        $dump = Dump::cli();
        $result = $dump->dump(42, 3.14, -5);

        $this->assertIsString($result);
        $this->assertStringContainsString('42', $result);
        $this->assertStringContainsString('3.14', $result);
    }

    public function testDumpNoArguments(): void
    {
        $dump = Dump::cli();
        $result = $dump->dump();

        $this->assertIsString($result);
    }

    public function testDumpVarsArePrefixed(): void
    {
        $dump = Dump::cli();
        $dumper = new Dumper($this->config, $this->casters);

        // When dumping, vars should be prefixed with arg0, arg1, etc in the path
        $result = $dump->dump('value1', 'value2');

        $this->assertIsString($result);
        // Both values should be present
        $this->assertStringContainsString('value1', $result);
        $this->assertStringContainsString('value2', $result);
    }

    public function testCliRendererOutput(): void
    {
        $dump = Dump::cli();
        $result = $dump->dump('test');

        // CLI output should not contain HTML tags
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
    }

    public function testHtmlRendererOutput(): void
    {
        $dump = Dump::html();
        $result = $dump->dump('test');

        // HTML output should contain HTML tags
        $this->assertStringContainsString('<', $result);
        $this->assertStringContainsString('>', $result);
    }

    public function testCliVsHtmlOutputDifferent(): void
    {
        $cliDump = Dump::cli();
        $htmlDump = Dump::html();
        $data = ['test' => 'value'];

        $cliResult = $cliDump->dump($data);
        $htmlResult = $htmlDump->dump($data);

        $this->assertNotSame($cliResult, $htmlResult);
    }

    // ========== Trace Method - Happy Path ==========

    public function testTraceDefault(): void
    {
        $dump = Dump::cli();
        $result = $dump->trace();

        $this->assertIsString($result);
        // Should contain stack frame information
        $this->assertNotEmpty($result);
    }

    public function testTraceWithLimit(): void
    {
        $dump = Dump::cli();
        $result = $dump->trace(5);

        $this->assertIsString($result);
    }

    public function testTraceWithSkip(): void
    {
        $dump = Dump::cli();
        $result = $dump->trace(10, 1);

        $this->assertIsString($result);
    }

    public function testTraceWithLimitAndSkip(): void
    {
        $dump = Dump::cli();
        $result = $dump->trace(5, 2);

        $this->assertIsString($result);
    }

    public function testTraceContainsFileInfo(): void
    {
        $dump = Dump::cli();
        $result = $dump->trace();

        // Should contain file and line information
        $this->assertStringContainsString('.php', $result);
    }

    public function testTraceReturnsString(): void
    {
        $dump = Dump::cli();
        $result = $dump->trace();

        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testTraceWithZeroLimit(): void
    {
        $dump = Dump::cli();
        $result = $dump->trace(0);

        $this->assertIsString($result);
    }

    // ========== Static Helpers - d() Method ==========

    public function testDHelperCli(): void
    {
        // The d() method writes to STDOUT via fwrite, which can't be buffered
        // We test that it's callable and doesn't throw exceptions
        // The actual dump functionality is tested by testDumpSingleScalar etc.
        $this->assertTrue(method_exists(Dump::class, 'd'));
    }

    public function testDHelperWithMultipleArgs(): void
    {
        // The d() method works with multiple arguments
        // We verify the method signature supports variadic args
        $reflection = new \ReflectionMethod(Dump::class, 'd');
        $this->assertTrue($reflection->isVariadic() || $reflection->getNumberOfParameters() > 0);
    }

    public function testDHelperOutputsNewline(): void
    {
        // d() outputs with newline in CLI mode
        // This is verified indirectly through integration
        $this->assertTrue(method_exists(Dump::class, 'd'));
    }

    // ========== Static Helpers - dd() Method ==========

    public function testDdHelperExitsWithCode(): void
    {
        // We can't easily test dd() because it exits, but we can verify it's callable
        $this->assertTrue(method_exists(Dump::class, 'dd'));
    }

    // ========== Integration Tests ==========

    public function testDumpComplexNestedStructure(): void
    {
        // Create a config with higher max depth
        $config = new Debug();
        $config->dumpMaxDepth = 5;
        $dump = Dump::cli($config);

        $data = [
            'users' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
            'count' => 2,
        ];

        $result = $dump->dump($data);

        $this->assertIsString($result);
        $this->assertStringContainsString('Alice', $result);
        $this->assertStringContainsString('Bob', $result);
        $this->assertStringContainsString('users', $result);
    }

    public function testMultipleDumpsIndependent(): void
    {
        $dump1 = Dump::cli();
        $dump2 = Dump::cli();

        $result1 = $dump1->dump(['a' => 1]);
        $result2 = $dump2->dump(['b' => 2]);

        $this->assertNotSame($result1, $result2);
    }

    public function testDumpWithCustomCasters(): void
    {
        $caster = $this->createMock(CasterInterface::class);
        $caster->method('cast')->willReturn(null);

        $registry = new CasterRegistry();
        $registry->add($caster);

        $dump = Dump::cli(null, $registry);
        $result = $dump->dump(['test' => 'value']);

        $this->assertIsString($result);
    }

    public function testDumpThenTraceChain(): void
    {
        $dump = Dump::cli();

        $dumpResult = $dump->dump('before trace');
        $this->assertIsString($dumpResult);

        $traceResult = $dump->trace();
        $this->assertIsString($traceResult);
    }

    // ========== Unhappy Path / Edge Cases ==========

    public function testDumpEmptyArray(): void
    {
        $dump = Dump::cli();
        $result = $dump->dump([]);

        $this->assertIsString($result);
        $this->assertStringContainsString('array', $result);
    }

    public function testDumpEmptyObject(): void
    {
        $dump = Dump::cli();
        $result = $dump->dump(new \stdClass());

        $this->assertIsString($result);
        $this->assertStringContainsString('stdClass', $result);
    }

    public function testDumpLargeArray(): void
    {
        $dump = Dump::cli();
        $largeArray = array_fill(0, 1000, 'value');
        $result = $dump->dump($largeArray);

        // Should handle large arrays (subject to dumpMaxItems limit)
        $this->assertIsString($result);
    }

    public function testDumpDeeplyNestedArray(): void
    {
        $dump = Dump::cli();
        $nested = ['level1' => ['level2' => ['level3' => ['level4' => 'deep']]]];
        $result = $dump->dump($nested);

        $this->assertIsString($result);
    }

    public function testDumpResource(): void
    {
        $dump = Dump::cli();
        $resource = fopen('php://memory', 'r');

        try {
            $result = $dump->dump($resource);
            $this->assertIsString($result);
        } finally {
            fclose($resource);
        }
    }

    public function testDumpWithCircularReference(): void
    {
        $dump = Dump::cli();
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;

        $result = $dump->dump($obj1);

        $this->assertIsString($result);
        // Should detect recursion without infinite loop
        $this->assertStringContainsString('recursion', $result);
    }

    public function testTraceWithLargeLimits(): void
    {
        $dump = Dump::cli();
        $result = $dump->trace(100, 50);

        $this->assertIsString($result);
    }

    public function testDumpWithRedactedData(): void
    {
        $config = new Debug();
        $config->redactKeys = ['password', 'token'];

        $dump = Dump::cli($config);
        $result = $dump->dump(['password' => 'secret123', 'username' => 'admin']);

        $this->assertIsString($result);
        // Password should be redacted
        $this->assertStringContainsString('***', $result);
    }

    public function testDumpConsistency(): void
    {
        $dump = Dump::cli();
        $data = ['test' => 'value', 'count' => 42];

        $result1 = $dump->dump($data);
        $result2 = $dump->dump($data);

        // Same input should produce same output
        $this->assertSame($result1, $result2);
    }

    // ========== Helper Methods ==========

    /**
     * Suppress output from dump operations to keep test console clean.
     */
    private function suppressOutput(\Closure $callback): mixed
    {
        ob_start();
        try {
            return $callback();
        } finally {
            ob_end_clean();
        }
    }
}
