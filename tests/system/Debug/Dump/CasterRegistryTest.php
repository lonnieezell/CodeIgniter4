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
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @internal
 */
#[Group('Others')]
final class CasterRegistryTest extends CIUnitTestCase
{
    private CasterRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new CasterRegistry();
    }

    public function testAddRegistersACaster(): void
    {
        $initialCount = count($this->registry->getCasters());
        $node = $this->createRealNode();
        $caster = $this->createMockCaster(returns: $node);

        $this->registry->add($caster);

        // Verify the caster was added directly
        $this->assertCount($initialCount + 1, $this->registry->getCasters());
        $this->assertContains($caster, $this->registry->getCasters());
    }

    public function testAddMultipleCastersInOrder(): void
    {
        $caster1 = $this->createMockCaster(returns: null);
        $caster2 = $this->createMockCaster(returns: $this->createRealNode());

        $this->registry->add($caster1);
        $this->registry->add($caster2);

        $casters = $this->registry->getCasters();
        $this->assertCount(2, $casters);
        $this->assertSame($caster1, $casters[0]);
        $this->assertSame($caster2, $casters[1]);
    }

    public function testCastReturnsNodeFromFirstMatchingCaster(): void
    {
        $node = $this->createRealNode();
        $caster = $this->createMockCaster(returns: $node);

        $this->registry->add($caster);

        $dumper = $this->createRealDumper();
        $result = $this->registry->cast('test', 'root', 0, $dumper);

        $this->assertSame($node, $result);
    }

    public function testCastPassesCorrectParametersToCaster(): void
    {
        $caster = $this->createMock(CasterInterface::class);
        $node = $this->createRealNode();

        $caster->expects($this->once())
            ->method('cast')
            ->with('myValue', 'root.property', 5, $this->isInstanceOf(Dumper::class))
            ->willReturn($node);

        $this->registry->add($caster);

        $dumper = $this->createRealDumper();
        $result = $this->registry->cast('myValue', 'root.property', 5, $dumper);

        $this->assertSame($node, $result);
    }

    public function testCastSkipsNullReturnsFromCasters(): void
    {
        $caster1 = $this->createMockCaster(returns: null);
        $caster2 = $this->createMockCaster(returns: null);
        $node = $this->createRealNode();
        $caster3 = $this->createMockCaster(returns: $node);

        $this->registry->add($caster1);
        $this->registry->add($caster2);
        $this->registry->add($caster3);

        $dumper = $this->createRealDumper();
        $result = $this->registry->cast('test', 'root', 0, $dumper);

        // Verify all three casters are registered
        $this->assertCount(3, $this->registry->getCasters());
        // And the result came from the third caster
        $this->assertSame($node, $result);
    }

    public function testCastIteratesCastersInRegistrationOrder(): void
    {
        $caster1 = $this->createMock(CasterInterface::class);
        $caster2 = $this->createMock(CasterInterface::class);

        $caster1->expects($this->once())->method('cast')->willReturn(null);
        $caster2->expects($this->once())->method('cast')->willReturn($this->createRealNode());

        $this->registry->add($caster1);
        $this->registry->add($caster2);

        $dumper = $this->createRealDumper();
        $this->registry->cast('test', 'root', 0, $dumper);
    }

    public function testConstructorCollectsSystemCasters(): void
    {
        $registry = new CasterRegistry();

        // The registry will contain system casters if any exist
        $casters = $registry->getCasters();
        $this->assertIsArray($casters);

        // Any casters that are present should be CasterInterface instances
        $this->assertContainsOnlyInstancesOf(CasterInterface::class, $casters);
    }

    public function testCastReturnsNullWhenNoCasterMatches(): void
    {
        $caster = $this->createMockCaster(returns: null);
        $this->registry->add($caster);

        $dumper = $this->createRealDumper();
        $result = $this->registry->cast('test', 'root', 0, $dumper);

        $this->assertNull($result);
    }

    public function testCastReturnsNullWhenNoClasstersRegistered(): void
    {
        // Create a new registry without system casters
        $registry = new CasterRegistry();

        // Clear casters by reflection to simulate an empty registry
        $reflection = new \ReflectionClass($registry);
        $property = $reflection->getProperty('casters');
        $property->setAccessible(true);
        $property->setValue($registry, []);

        // Verify the registry is empty
        $this->assertEmpty($registry->getCasters());

        $dumper = $this->createRealDumper();
        $result = $registry->cast('test', 'root', 0, $dumper);

        $this->assertNull($result);
    }

    public function testCastStopsAfterFirstMatch(): void
    {
        $node = $this->createRealNode();
        $caster1 = $this->createMockCaster(returns: $node);
        $caster2 = $this->createMock(CasterInterface::class);

        $caster2->expects($this->never())->method('cast');

        $this->registry->add($caster1);
        $this->registry->add($caster2);

        $dumper = $this->createRealDumper();
        $this->registry->cast('test', 'root', 0, $dumper);
    }

    public function testCastWithDifferentValueTypes(): void
    {
        $caster = $this->createMockCaster(returns: $this->createRealNode());
        $this->registry->add($caster);
        $dumper = $this->createRealDumper();

        // Test with different value types
        $values = [
            'string',
            123,
            12.34,
            true,
            false,
            null,
            [],
            new \stdClass(),
        ];

        foreach ($values as $value) {
            $result = $this->registry->cast($value, 'root', 0, $dumper);
            $this->assertNotNull($result, sprintf('Failed for type: %s', get_debug_type($value)));
        }
    }

    public function testCastWithDifferentDepths(): void
    {
        $caster = $this->createMockCaster(returns: $this->createRealNode());
        $this->registry->add($caster);
        $dumper = $this->createRealDumper();

        // Test with different depth values
        for ($depth = 0; $depth <= 10; $depth++) {
            $result = $this->registry->cast('test', 'root', $depth, $dumper);
            $this->assertNotNull($result, sprintf('Failed for depth: %d', $depth));
        }
    }

    /**
     * Create a mock caster with optional return value.
     */
    private function createMockCaster(?Node $returns = null): MockObject&CasterInterface
    {
        $caster = $this->createMock(CasterInterface::class);

        if ($returns === null) {
            $caster->method('cast')->willReturn(null);
        } else {
            $caster->method('cast')->willReturn($returns);
        }

        return $caster;
    }

    /**
     * Create a real Dumper instance.
     */
    private function createRealDumper(): Dumper
    {
        return new Dumper(null, null);
    }

    /**
     * Create a real Node instance.
     */
    private function createRealNode(): Node
    {
        return new Node(
            kind: Node::KIND_SCALAR,
            type: 'string',
            summary: "'test'",
        );
    }
}
