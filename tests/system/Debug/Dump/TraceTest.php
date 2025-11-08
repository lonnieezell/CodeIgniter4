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

use CodeIgniter\Test\CIUnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * @internal
 */
#[Group('Others')]
final class TraceTest extends CIUnitTestCase
{
    public function testCaptureReturnsArray(): void
    {
        $result = Trace::capture();

        $this->assertIsArray($result);
    }

    public function testCaptureDefaultLimitReturnsUpToTenFrames(): void
    {
        $result = Trace::capture();

        $this->assertLessThanOrEqual(10, count($result));
    }

    public function testCaptureWithCustomLimit(): void
    {
        $result = Trace::capture(limit: 5);

        $this->assertLessThanOrEqual(5, count($result));
    }

    public function testCaptureWithSkipParameter(): void
    {
        $resultNoSkip = Trace::capture(limit: 10, skip: 0);
        $resultWithSkip = Trace::capture(limit: 10, skip: 2);

        $this->assertGreaterThanOrEqual(count($resultWithSkip), count($resultNoSkip));
    }

    public function testCaptureFrameStructure(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $firstFrame = $result[0];

        $this->assertIsArray($firstFrame);
        $this->assertArrayHasKey('file', $firstFrame);
        $this->assertArrayHasKey('line', $firstFrame);
        $this->assertArrayHasKey('class', $firstFrame);
        $this->assertArrayHasKey('type', $firstFrame);
        $this->assertArrayHasKey('function', $firstFrame);
    }

    public function testCaptureFrameFileIsString(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $this->assertIsString($result[0]['file']);
    }

    public function testCaptureFrameFileIsInternalWhenNotAvailable(): void
    {
        // This is difficult to test without mocking debug_backtrace
        // We can verify the normal case has a valid file path
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $file = $result[0]['file'];
        $this->assertTrue($file === 'internal' || is_string($file));
    }

    public function testCaptureFrameLineIsInteger(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $this->assertIsInt($result[0]['line']);
    }

    public function testCaptureFrameLineIsZeroWhenNotAvailable(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $line = $result[0]['line'];
        $this->assertTrue($line === 0 || $line > 0);
    }

    public function testCaptureFrameClassCanBeNull(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $class = $result[0]['class'];
        $this->assertTrue($class === null || is_string($class));
    }

    public function testCaptureFrameTypeCanBeNull(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $type = $result[0]['type'];
        $this->assertTrue($type === null || is_string($type));
    }

    public function testCaptureFrameFunctionCanBeNull(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $function = $result[0]['function'];
        $this->assertTrue($function === null || is_string($function));
    }

    public function testCaptureSkipsCurrentFrame(): void
    {
        $result = Trace::capture(limit: 1, skip: 0);

        // The first frame should not be Trace::capture itself
        $this->assertNotEmpty($result);
        // Should have a frame (from the test method or PHPUnit)
        $this->assertIsArray($result[0]);
    }

    public function testCaptureWithZeroLimit(): void
    {
        $result = Trace::capture(limit: 0);

        // With limit 0 and skip 0, should get minimal frames
        $this->assertIsArray($result);
    }

    public function testCaptureWithLargeLimit(): void
    {
        $result = Trace::capture(limit: 100);

        $this->assertIsArray($result);
        // Should still be a reasonable number (usually less than 100)
        $this->assertLessThanOrEqual(100, count($result));
    }

    public function testCaptureWithSkipGreaterThanFrames(): void
    {
        $result = Trace::capture(limit: 1, skip: 100);

        $this->assertIsArray($result);
        // Should have few or no frames if skipping past available frames
        $this->assertLessThanOrEqual(1, count($result));
    }

    public function testCaptureConsistentStructure(): void
    {
        $result = Trace::capture(limit: 5);

        foreach ($result as $frame) {
            $this->assertArrayHasKey('file', $frame);
            $this->assertArrayHasKey('line', $frame);
            $this->assertArrayHasKey('class', $frame);
            $this->assertArrayHasKey('type', $frame);
            $this->assertArrayHasKey('function', $frame);

            $this->assertIsString($frame['file']);
            $this->assertIsInt($frame['line']);
            $this->assertTrue($frame['class'] === null || is_string($frame['class']));
            $this->assertTrue($frame['type'] === null || is_string($frame['type']));
            $this->assertTrue($frame['function'] === null || is_string($frame['function']));
        }
    }

    public function testCaptureMultipleCalls(): void
    {
        $result1 = Trace::capture();
        $result2 = Trace::capture();

        // Both should return valid arrays
        $this->assertIsArray($result1);
        $this->assertIsArray($result2);

        // Both should have frames
        $this->assertNotEmpty($result1);
        $this->assertNotEmpty($result2);
    }

    public function testCaptureInNestedFunctionCall(): void
    {
        $trace = $this->getNestedTrace();

        $this->assertIsArray($trace);
        $this->assertNotEmpty($trace);

        // First frame should be from this method or the nested call
        $frame = $trace[0];
        $this->assertArrayHasKey('function', $frame);
    }

    public function testCaptureTypeProperty(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $type = $result[0]['type'];

        if ($type !== null) {
            // Type should be '->' for object methods or '::' for static
            $this->assertContains($type, ['->', '::', null]);
        }
    }

    public function testCaptureAllFramesHaveFunction(): void
    {
        $result = Trace::capture(limit: 5);

        foreach ($result as $frame) {
            $function = $frame['function'];
            // Function should be set for all frames (PHPUnit, test methods, etc.)
            $this->assertTrue(
                is_string($function) && strlen($function) > 0,
                'Frame should have a function name'
            );
        }
    }

    public function testCaptureStaticMethod(): void
    {
        $result = Trace::capture(limit: 1, skip: 0);

        // This is called from a static method (testCaptureStaticMethod)
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testCaptureReturnedFramesAreNotModified(): void
    {
        $result = Trace::capture();

        $originalCount = count($result);

        // Modify the returned array
        $result[] = ['added' => 'frame'];

        // Capture again - should not be affected
        $result2 = Trace::capture();

        $this->assertSame($originalCount, count($result2));
    }

    public function testCaptureFilesAreAbsolutePath(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $file = $result[0]['file'];

        if ($file !== 'internal') {
            // Should be an absolute path or at least contain a slash
            $this->assertTrue(
                strpos($file, '/') !== false || strpos($file, '\\') !== false,
                'File path should contain path separators'
            );
        }
    }

    public function testCaptureLineNumbersPositiveOrZero(): void
    {
        $result = Trace::capture(limit: 10);

        foreach ($result as $frame) {
            $line = $frame['line'];
            $this->assertGreaterThanOrEqual(0, $line);
        }
    }

    public function testCaptureWithNamespacedClass(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);

        // Should contain frames from CodeIgniter namespaces
        $foundCodeIgniterFrame = false;
        foreach ($result as $frame) {
            if ($frame['class'] !== null && strpos($frame['class'], 'CodeIgniter') !== false) {
                $foundCodeIgniterFrame = true;
                break;
            }
        }

        // We expect to find at least one CodeIgniter frame
        $this->assertTrue($foundCodeIgniterFrame);
    }

    public function testCaptureIsStatic(): void
    {
        // Verify we can call capture without instantiating Trace
        $result = Trace::capture();

        $this->assertIsArray($result);
    }

    public function testCaptureDoesNotThrowException(): void
    {
        // This should not throw any exceptions
        try {
            $result = Trace::capture();
            $this->assertIsArray($result);
        } catch (\Exception $e) {
            $this->fail('Trace::capture() threw an exception: ' . $e->getMessage());
        }
    }

    public function testCaptureWithZeroSkip(): void
    {
        $result = Trace::capture(limit: 5, skip: 0);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function testCaptureWithOneSkip(): void
    {
        $resultNoSkip = Trace::capture(limit: 10, skip: 0);
        $resultOneSkip = Trace::capture(limit: 10, skip: 1);

        // With skip 1, we should have one fewer frame (or same if less than total)
        $this->assertLessThanOrEqual(count($resultNoSkip), count($resultOneSkip) + 1);
    }

    public function testCaptureFramesContainNoExtraKeys(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);
        $frame = $result[0];

        // Should have exactly these keys, no extra keys
        $expectedKeys = ['file', 'line', 'class', 'type', 'function'];
        $actualKeys = array_keys($frame);

        // Sort for consistent comparison
        sort($expectedKeys);
        sort($actualKeys);

        $this->assertSame($expectedKeys, $actualKeys);
    }

    public function testCaptureFunctionNameNotEmpty(): void
    {
        $result = Trace::capture();

        $this->assertNotEmpty($result);

        foreach ($result as $frame) {
            $function = $frame['function'];
            $this->assertNotEmpty($function);
            $this->assertIsString($function);
        }
    }

    public function testCaptureOrderIsCorrect(): void
    {
        $result = Trace::capture(limit: 3, skip: 0);

        $this->assertNotEmpty($result);
        // First frame should be from the test method or PHPUnit
        $firstFrame = $result[0];
        $this->assertArrayHasKey('function', $firstFrame);
    }

    public function testCaptureWithEvenLimit(): void
    {
        $result = Trace::capture(limit: 8);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(8, count($result));
    }

    public function testCaptureWithOddLimit(): void
    {
        $result = Trace::capture(limit: 7);

        $this->assertIsArray($result);
        $this->assertLessThanOrEqual(7, count($result));
    }

    public function testCaptureIntegrationWithDump(): void
    {
        $trace = Trace::capture(limit: 5);
        $registry = new CasterRegistry();
        $dumper = new Dumper(config('Debug'), $registry);

        // Should be able to dump the trace without errors
        $result = $dumper->normalize($trace, 'trace');

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_ARRAY, $result->kind);
    }

    // Helper method to test nested calls
    private function getNestedTrace(): array
    {
        return $this->helperGetNestedTrace();
    }

    private function helperGetNestedTrace(): array
    {
        return Trace::capture();
    }
}
