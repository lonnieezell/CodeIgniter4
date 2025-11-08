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

/**
 * @internal
 */
#[Group('Others')]
final class DumperTest extends CIUnitTestCase
{
    private Dumper $dumper;
    private CasterRegistry $registry;
    private Debug $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = config('Debug');
        $this->registry = new CasterRegistry();
        $this->dumper = new Dumper($this->config, $this->registry);
    }

    public function testConstructorWithNullConfigUsesDefault(): void
    {
        $dumper = new Dumper(null, $this->registry);
        $this->assertInstanceOf(Dumper::class, $dumper);
    }

    public function testConstructorWithNullCastersCreatesNewRegistry(): void
    {
        $dumper = new Dumper($this->config, null);
        $this->assertInstanceOf(CasterRegistry::class, $dumper->casters());
    }

    public function testCastersReturnsRegistry(): void
    {
        $this->assertInstanceOf(CasterRegistry::class, $this->dumper->casters());
        $this->assertSame($this->registry, $this->dumper->casters());
    }

    public function testNormalizeNull(): void
    {
        $result = $this->dumper->normalize(null);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_NULL, $result->kind);
        $this->assertSame('null', $result->type);
        $this->assertSame('null', $result->summary);
    }

    public function testNormalizeScalarBoolean(): void
    {
        $result = $this->dumper->normalize(true);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_SCALAR, $result->kind);
        $this->assertSame('true', $result->summary);
    }

    public function testNormalizeScalarInt(): void
    {
        $result = $this->dumper->normalize(42);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_SCALAR, $result->kind);
        $this->assertSame('int(42)', $result->summary);
    }

    public function testNormalizeScalarFloat(): void
    {
        $result = $this->dumper->normalize(3.14);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_SCALAR, $result->kind);
        $this->assertStringContainsString('float', $result->summary);
    }

    public function testNormalizeString(): void
    {
        $result = $this->dumper->normalize('hello');

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_SCALAR, $result->kind);
        $this->assertSame('string', $result->type);
        $this->assertStringContainsString('hello', $result->summary);
    }

    public function testNormalizeStringWithLength(): void
    {
        $result = $this->dumper->normalize('hello world');

        // String format is: string(length) 'preview'
        $this->assertStringContainsString('string(', $result->summary);
        $this->assertStringContainsString('hello world', $result->summary);
        $this->assertIsArray($result->meta);
        $this->assertArrayHasKey('length', $result->meta);
        $this->assertSame(11, $result->meta['length']);
    }

    public function testNormalizeEmptyArray(): void
    {
        $result = $this->dumper->normalize([]);

        $this->assertInstanceOf(Node::class, $result);
        $this->assertSame(Node::KIND_ARRAY, $result->kind);
        $this->assertSame('array(0)', $result->summary);
        $this->assertEmpty($result->children ?? []);
    }

    public function testNormalizeArrayWithValues(): void
    {
        $result = $this->dumper->normalize(['a' => 1, 'b' => 2]);

        $this->assertSame(Node::KIND_ARRAY, $result->kind);
        $this->assertSame('array(2)', $result->summary);
        $this->assertCount(2, $result->children ?? []);
    }

    public function testNormalizeArrayPreservesOrder(): void
    {
        $result = $this->dumper->normalize(['first' => 1, 'second' => 2, 'third' => 3]);

        $children = $result->children ?? [];
        $keys = array_keys($children);

        $this->assertSame(['first', 'second', 'third'], $keys);
    }

    public function testNormalizeEmptyObject(): void
    {
        $obj = new \stdClass();
        $result = $this->dumper->normalize($obj);

        $this->assertSame(Node::KIND_OBJECT, $result->kind);
        $this->assertSame('stdClass', $result->type);
    }

    public function testNormalizeObjectWithProperties(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $obj->value = 42;

        $result = $this->dumper->normalize($obj);

        $this->assertSame(Node::KIND_OBJECT, $result->kind);
        $this->assertIsArray($result->children ?? []);
        $this->assertArrayHasKey('name', $result->children ?? []);
        $this->assertArrayHasKey('value', $result->children ?? []);
    }

    public function testNormalizeWithCustomPath(): void
    {
        $result = $this->dumper->normalize('value', 'custom.path');

        $this->assertSame('custom.path', $result->meta['path'] ?? null);
    }

    public function testNormalizeWithCustomDepth(): void
    {
        // Default max depth is 3, so depth 5 should exceed it
        $config = $this->createMockConfig(dumpMaxDepth: 4);
        $dumper = new Dumper($config, $this->registry);
        $result = $dumper->normalize('value', 'root', 4);

        $this->assertSame(Node::KIND_UNKNOWN, $result->kind);
    }

    public function testNormalizeNestedArray(): void
    {
        $data = [
            'outer' => [
                'inner' => 'value'
            ]
        ];

        $result = $this->dumper->normalize($data);

        $this->assertSame(Node::KIND_ARRAY, $result->kind);
        $this->assertArrayHasKey('outer', $result->children ?? []);

        $outerNode = $result->children['outer'] ?? null;
        $this->assertInstanceOf(Node::class, $outerNode);
        $this->assertArrayHasKey('inner', $outerNode->children ?? []);
    }

    public function testNormalizeUsesCustomCaster(): void
    {
        $caster = $this->createMock(CasterInterface::class);
        $customNode = new Node(
            kind: Node::KIND_SCALAR,
            type: 'custom',
            summary: 'custom cast'
        );

        $caster->expects($this->once())
            ->method('cast')
            ->willReturn($customNode);

        $this->registry->add($caster);

        $result = $this->dumper->normalize('test', 'root', 0);

        $this->assertSame($customNode, $result);
    }

    public function testNormalizeMaxDepthExceeded(): void
    {
        $config = $this->createMockConfig(dumpMaxDepth: 2);
        $dumper = new Dumper($config, $this->registry);

        $result = $dumper->normalize('value', 'root', 2);

        $this->assertSame(Node::KIND_UNKNOWN, $result->kind);
        $this->assertStringContainsString('max depth', $result->summary);
    }

    public function testNormalizeLongStringTruncated(): void
    {
        $config = $this->createMockConfig(dumpMaxStringLength: 10);
        $dumper = new Dumper($config, $this->registry);

        $longString = str_repeat('x', 50);
        $result = $dumper->normalize($longString);

        // String should be truncated at max length and marked as truncated
        $this->assertTrue($result->meta['truncated'] ?? false);
        $this->assertStringContainsString('…', $result->summary);
    }

    public function testNormalizeArrayLimitedByMaxItems(): void
    {
        $config = $this->createMockConfig(dumpMaxItems: 2);
        $dumper = new Dumper($config, $this->registry);

        $array = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4];
        $result = $dumper->normalize($array);

        $children = $result->children ?? [];
        $this->assertCount(3, $children); // 2 items + "…" indicator
        $this->assertArrayHasKey('…', $children);
    }

    public function testNormalizeObjectRecursion(): void
    {
        $obj1 = new \stdClass();
        $obj2 = new \stdClass();
        $obj1->ref = $obj2;
        $obj2->ref = $obj1;

        $result = $this->dumper->normalize($obj1);

        $this->assertSame(Node::KIND_OBJECT, $result->kind);
        // Second object should be marked as recursion
        $children = $result->children ?? [];
        $this->assertArrayHasKey('ref', $children);
    }

    public function testNormalizeArrayWithIntegerKeys(): void
    {
        $result = $this->dumper->normalize([0 => 'zero', 1 => 'one']);

        $this->assertSame(Node::KIND_ARRAY, $result->kind);
        $children = $result->children ?? [];
        $this->assertArrayHasKey('0', $children);
        $this->assertArrayHasKey('1', $children);
    }

    public function testNormalizeArrayWithMixedKeys(): void
    {
        $result = $this->dumper->normalize([0 => 'zero', 'name' => 'test', 2 => 'two']);

        $children = $result->children ?? [];
        $this->assertArrayHasKey('0', $children);
        $this->assertArrayHasKey('name', $children);
        $this->assertArrayHasKey('2', $children);
    }

    public function testNormalizeResource(): void
    {
        $resource = fopen('php://memory', 'r');

        try {
            $result = $this->dumper->normalize($resource);

            $this->assertSame(Node::KIND_RESOURCE, $result->kind);
        } finally {
            fclose($resource);
        }
    }

    public function testNormalizeNestedPath(): void
    {
        $data = ['a' => ['b' => ['c' => 'value']]];
        $result = $this->dumper->normalize($data);

        $aNode = $result->children['a'] ?? null;
        $this->assertSame('root.a', $aNode->meta['path'] ?? null);

        $bNode = $aNode->children['b'] ?? null;
        $this->assertSame('root.a.b', $bNode->meta['path'] ?? null);

        $cNode = $bNode->children['c'] ?? null;
        $this->assertSame('root.a.b.c', $cNode->meta['path'] ?? null);
    }

    public function testNormalizeBooleanFalse(): void
    {
        $result = $this->dumper->normalize(false);

        $this->assertSame('false', $result->summary);
    }

    public function testNormalizeZero(): void
    {
        $result = $this->dumper->normalize(0);

        $this->assertSame('int(0)', $result->summary);
    }

    public function testNormalizeEmptyString(): void
    {
        $result = $this->dumper->normalize('');

        // Empty string should have summary like: string(0) ''
        $this->assertStringContainsString('string(0)', $result->summary);
    }

    // ========== Redaction Tests ==========

    public function testNormalizeArrayWithRedactedKey(): void
    {
        $config = $this->createMockConfig(shouldRedact: true, redactKey: 'password');
        $dumper = new Dumper($config, $this->registry);

        $data = ['username' => 'admin', 'password' => 'secret'];
        $result = $dumper->normalize($data);

        $children = $result->children ?? [];
        $passwordNode = $children['password'] ?? null;

        $this->assertNotNull($passwordNode);
        $this->assertSame('***', $passwordNode->summary);
        $this->assertTrue($passwordNode->meta['redacted'] ?? false);
    }

    public function testNormalizeObjectWithRedactedProperty(): void
    {
        $config = $this->createMockConfig(shouldRedact: true, redactKey: 'secret');
        $dumper = new Dumper($config, $this->registry);

        $obj = new \stdClass();
        $obj->public = 'visible';
        $obj->secret = 'hidden';

        $result = $dumper->normalize($obj);

        $children = $result->children ?? [];
        $secretNode = $children['secret'] ?? null;

        $this->assertNotNull($secretNode);
        $this->assertSame('***', $secretNode->summary);
        $this->assertTrue($secretNode->meta['redacted'] ?? false);
    }

    public function testNormalizeComplexStructure(): void
    {
        $data = [
            'user' => (object)[
                'id' => 1,
                'name' => 'John',
                'emails' => ['john@example.com', 'john.doe@example.com'],
            ],
            'meta' => [
                'created' => '2024-01-01',
                'tags' => ['admin', 'user'],
            ],
        ];

        $result = $this->dumper->normalize($data);

        $this->assertSame(Node::KIND_ARRAY, $result->kind);
        $this->assertArrayHasKey('user', $result->children ?? []);
        $this->assertArrayHasKey('meta', $result->children ?? []);
    }

    public function testMultipleNormalizeCallsIndependent(): void
    {
        $result1 = $this->dumper->normalize(['a' => 1]);
        $result2 = $this->dumper->normalize(['b' => 2]);

        $this->assertNotSame($result1, $result2);
        $this->assertNotSame(
            $result1->children ?? [],
            $result2->children ?? []
        );
    }

    /**
     * Create a mock Debug config with custom settings.
     */
    private function createMockConfig(
        ?int $dumpMaxDepth = null,
        ?int $dumpMaxStringLength = null,
        ?int $dumpMaxItems = null,
        bool $dumpSafeMode = false,
        bool $shouldRedact = false,
        ?string $redactKey = null,
    ): Debug {
        $config = new Debug();

        if ($dumpMaxDepth !== null) {
            $config->dumpMaxDepth = $dumpMaxDepth;
        }

        if ($dumpMaxStringLength !== null) {
            $config->dumpMaxStringLength = $dumpMaxStringLength;
        }

        if ($dumpMaxItems !== null) {
            $config->dumpMaxItems = $dumpMaxItems;
        }

        $config->dumpSafeMode = $dumpSafeMode;

        if ($shouldRedact && $redactKey) {
            $config->redactKeys = [$redactKey];
        }

        return $config;
    }
}
