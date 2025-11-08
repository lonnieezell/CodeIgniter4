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
final class DiffTest extends CIUnitTestCase
{
    private Dumper $dumper;
    private CasterRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new CasterRegistry();
        $this->dumper = new Dumper(config('Debug'), $this->registry);
    }

    public function testDiffIdenticalArrays(): void
    {
        $a = ['key' => 'value'];
        $b = ['key' => 'value'];

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_ARRAY, $result->kind);
        $this->assertSame('diff', $result->type);
        // Should have no children since arrays are identical
        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffIdenticalObjects(): void
    {
        $a = new \stdClass();
        $a->prop = 'value';

        $b = new \stdClass();
        $b->prop = 'value';

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_ARRAY, $result->kind);
        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffIdenticalScalars(): void
    {
        $result = Diff::diff('test', 'test', $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_ARRAY, $result->kind);
    }

    public function testDiffIdenticalEmptyArrays(): void
    {
        $result = Diff::diff([], [], $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffAddedSingleKey(): void
    {
        $a = [];
        $b = ['new' => 'value'];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('+ added', $children);

        $addedNode = $children['+ added'];
        $this->assertSame(Node::KIND_ARRAY, $addedNode->kind);
        $this->assertArrayHasKey('new', $addedNode->children ?? []);
    }

    public function testDiffAddedMultipleKeys(): void
    {
        $a = ['existing' => 1];
        $b = ['existing' => 1, 'new1' => 2, 'new2' => 3];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('+ added', $children);

        $addedNode = $children['+ added'];
        $addedChildren = $addedNode->children ?? [];
        $this->assertArrayHasKey('new1', $addedChildren);
        $this->assertArrayHasKey('new2', $addedChildren);
    }

    public function testDiffAddedObjectProperty(): void
    {
        $a = new \stdClass();
        $a->existing = 'value';

        $b = new \stdClass();
        $b->existing = 'value';
        $b->new = 'property';

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('+ added', $children);
    }

    public function testDiffRemovedMultipleKeys(): void
    {
        $a = ['removed1' => 1, 'removed2' => 2, 'kept' => 3];
        $b = ['kept' => 3];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('- removed', $children);

        $removedNode = $children['- removed'];
        $removedChildren = $removedNode->children ?? [];
        $this->assertCount(2, $removedChildren);
        $this->assertArrayHasKey('removed1', $removedChildren);
        $this->assertArrayHasKey('removed2', $removedChildren);
    }

    public function testDiffRemovedFromFullArray(): void
    {
        $a = ['a' => 1, 'b' => 2, 'c' => 3];
        $b = [];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('- removed', $children);
    }

    public function testDiffRemovedObjectProperty(): void
    {
        $a = new \stdClass();
        $a->removed = 'value';
        $a->kept = 'value';

        $b = new \stdClass();
        $b->kept = 'value';

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('- removed', $children);
    }

    public function testDiffChangedMultipleValues(): void
    {
        $a = ['a' => 1, 'b' => 2, 'c' => 3];
        $b = ['a' => 10, 'b' => 20, 'c' => 3];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('~ changed', $children);

        $changedNode = $children['~ changed'];
        $changedChildren = $changedNode->children ?? [];
        $this->assertCount(2, $changedChildren);
    }

    public function testDiffChangedValueStructure(): void
    {
        $a = ['key' => 'old'];
        $b = ['key' => 'new'];

        $result = Diff::diff($a, $b, $this->dumper);

        $changedNode = $result->children['~ changed'] ?? null;
        $this->assertNotNull($changedNode);

        $keyNode = $changedNode->children['key'] ?? null;
        $this->assertNotNull($keyNode);

        $pair = $keyNode->children ?? [];
        $this->assertArrayHasKey('from', $pair);
        $this->assertArrayHasKey('to', $pair);
    }

    public function testDiffChangedObjectProperty(): void
    {
        $a = new \stdClass();
        $a->prop = 'old';

        $b = new \stdClass();
        $b->prop = 'new';

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('~ changed', $children);
    }

    public function testDiffChangedToNull(): void
    {
        $a = ['key' => 'value'];
        $b = ['key' => null];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('~ changed', $children);
    }

    public function testDiffChangedFromNull(): void
    {
        $a = ['key' => null];
        $b = ['key' => 'value'];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('~ changed', $children);
    }

    public function testDiffAddedRemovedAndChanged(): void
    {
        $a = ['kept' => 'same', 'changed' => 'old', 'removed' => 'gone'];
        $b = ['kept' => 'same', 'changed' => 'new', 'added' => 'new'];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('+ added', $children);
        $this->assertArrayHasKey('- removed', $children);
        $this->assertArrayHasKey('~ changed', $children);
    }

    public function testDiffMixedTypesInArray(): void
    {
        $a = [
            'string' => 'text',
            'int' => 42,
            'bool' => true,
            'array' => [1, 2, 3],
            'object' => new \stdClass(),
        ];
        $b = [
            'string' => 'changed',
            'int' => 42,
            'bool' => false,
            'array' => [1, 2, 3],
            'object' => new \stdClass(),
        ];

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
        $children = $result->children ?? [];
        // String, bool, and object changed
        $this->assertArrayHasKey('~ changed', $children);
    }

    public function testDiffNestedArrays(): void
    {
        $a = ['nested' => ['inner' => 'value']];
        $b = ['nested' => ['inner' => 'changed']];

        $result = Diff::diff($a, $b, $this->dumper);

        // Diff is shallow, so it treats nested arrays as different
        $children = $result->children ?? [];
        $this->assertArrayHasKey('~ changed', $children);
    }

    public function testDiffWithNumericKeys(): void
    {
        $a = [0 => 'zero', 1 => 'one', 2 => 'two'];
        $b = [0 => 'zero', 1 => 'one', 3 => 'three'];

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        // Key 2 removed, key 3 added
        $this->assertArrayHasKey('+ added', $children);
        $this->assertArrayHasKey('- removed', $children);
    }

    public function testDiffObjectVsArray(): void
    {
        $a = (object)['key' => 'value'];
        $b = ['key' => 'value'];

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
    }

    public function testDiffArrayVsObject(): void
    {
        $a = ['key' => 'value'];
        $b = (object)['key' => 'value'];

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
    }

    public function testDiffScalarEquality(): void
    {
        // Test scalar types are compared correctly
        $result = Diff::diff(42, 42, $this->dumper);
        $children = $result->children ?? [];
        $this->assertEmpty($children);
    }

    public function testDiffScalarInequality(): void
    {
        $result = Diff::diff(42, 43, $this->dumper);
        $children = $result->children ?? [];
        // Scalars can't have keys, so they convert to arrays
        $this->assertIsArray($children);
    }

    public function testDiffBooleanEquality(): void
    {
        $result = Diff::diff(true, true, $this->dumper);
        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffBooleanInequality(): void
    {
        $result = Diff::diff(true, false, $this->dumper);
        // When converted to arrays for comparison
        $this->assertInstanceOf(Node::class, $result);
    }

    public function testDiffStringEquality(): void
    {
        $result = Diff::diff('same', 'same', $this->dumper);
        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffStringInequality(): void
    {
        $result = Diff::diff('first', 'second', $this->dumper);
        $this->assertInstanceOf(Node::class, $result);
    }

    public function testDiffEmptyObjectToEmptyArray(): void
    {
        $a = new \stdClass();
        $b = [];

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffNullValues(): void
    {
        $a = null;
        $b = null;

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffNullVsEmpty(): void
    {
        $a = null;
        $b = [];

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertInstanceOf(Node::class, $result);
    }

    public function testDiffLargeArrays(): void
    {
        $a = array_fill(0, 100, 'value');
        $b = array_fill(0, 100, 'value');

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffLargeArraysWithChanges(): void
    {
        $a = array_fill(0, 100, 'old');
        $b = array_fill(0, 100, 'new');

        $result = Diff::diff($a, $b, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('~ changed', $children);
    }

    public function testDiffWithSpecialCharacters(): void
    {
        $a = ['key' => "value\nwith\nnewlines"];
        $b = ['key' => "value\nwith\nnewlines"];

        $result = Diff::diff($a, $b, $this->dumper);

        $this->assertEmpty($result->children ?? []);
    }

    public function testDiffComplexObjectStructure(): void
    {
        $obj1 = new \stdClass();
        $obj1->id = 1;
        $obj1->name = 'Alice';
        $obj1->email = 'alice@example.com';

        $obj2 = new \stdClass();
        $obj2->id = 1;
        $obj2->name = 'Alice';
        $obj2->email = 'alice.new@example.com';

        $result = Diff::diff($obj1, $obj2, $this->dumper);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('~ changed', $children);

        $changedNode = $children['~ changed'];
        $this->assertArrayHasKey('email', $changedNode->children ?? []);
    }

    public function testDiffUsesCustomDumper(): void
    {
        $caster = $this->createMock(CasterInterface::class);
        $caster->method('cast')->willReturn(null);

        $registry = new CasterRegistry();
        $registry->add($caster);

        $dumper = new Dumper(config('Debug'), $registry);
        $result = Diff::diff(['a' => 1], ['a' => 2], $dumper);

        $this->assertInstanceOf(Node::class, $result);
    }

    public function testDiffPathGeneration(): void
    {
        $result = Diff::diff(['key' => 'old'], ['key' => 'new'], $this->dumper);

        $changedNode = $result->children['~ changed'];
        $keyNode = $changedNode->children['key'];
        $pairChildren = $keyNode->children;

        // Verify paths are generated for diff context
        $this->assertNotNull($pairChildren['from']->meta['path'] ?? null);
        $this->assertNotNull($pairChildren['to']->meta['path'] ?? null);
    }
}
