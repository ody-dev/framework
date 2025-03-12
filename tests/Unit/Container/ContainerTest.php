<?php
/*
 * This file is part of ODY framework
 *
 * @link https://ody.dev
 * @documentation https://ody.dev/docs
 * @license https://github.com/ody-dev/ody-core/blob/master/LICENSE
 */

namespace Tests\Unit\Container;

use Ody\Container\Container;
use Ody\Container\Contracts\BindingResolutionException;
use Ody\Container\Contracts\CircularDependencyException;
use Ody\Container\EntryNotFoundException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use stdClass;

class ContainerTest extends TestCase
{
    /**
     * @var Container
     */
    protected $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testImplementsPsrContainer()
    {
        $this->assertInstanceOf(ContainerInterface::class, $this->container);
    }

    public function testBindAndResolve()
    {
        $this->container->bind('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $this->container->get('foo'));
    }

    public function testBindAndMake()
    {
        $this->container->bind('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $this->container->make('foo'));
    }

    public function testSingletonBinding()
    {
        $this->container->singleton('foo', function () {
            return new stdClass();
        });

        $instance1 = $this->container->get('foo');
        $instance2 = $this->container->get('foo');

        $this->assertSame($instance1, $instance2);
    }

    public function testResolvingConcreteClass()
    {
        $instance = $this->container->make(SimpleClass::class);
        $this->assertInstanceOf(SimpleClass::class, $instance);
    }

    public function testResolvingWithDependencies()
    {
        $instance = $this->container->make(ClassWithDependency::class);
        $this->assertInstanceOf(ClassWithDependency::class, $instance);
        $this->assertInstanceOf(SimpleClass::class, $instance->dependency);
    }

    public function testArrayAccess()
    {
        $this->container['foo'] = 'bar';
        $this->assertEquals('bar', $this->container['foo']);
        $this->assertTrue(isset($this->container['foo']));
        unset($this->container['foo']);
        $this->assertFalse(isset($this->container['foo']));
    }

    public function testAliasBinding()
    {
        $this->container->bind('foo', function () {
            return 'bar';
        });

        $this->container->alias('foo', 'baz');
        $this->assertEquals('bar', $this->container->get('baz'));
    }

    public function testInstanceBinding()
    {
        $object = new stdClass();
        $object->foo = 'bar';

        $this->container->instance('object', $object);

        $this->assertSame($object, $this->container->get('object'));
    }

    public function testBound()
    {
        $this->container->bind('foo', function () {
            return 'bar';
        });

        $this->assertTrue($this->container->bound('foo'));
        $this->assertFalse($this->container->bound('baz'));
    }

    public function testForgetInstance()
    {
        $this->container->instance('foo', new stdClass());
        $this->assertTrue($this->container->bound('foo'));

        $this->container->forgetInstance('foo');
        $this->assertFalse(isset($this->container['foo']));
    }

    public function testForgetInstances()
    {
        $this->container->instance('foo', new stdClass());
        $this->container->instance('bar', new stdClass());

        $this->container->forgetInstances();

        $this->assertFalse(isset($this->container['foo']));
        $this->assertFalse(isset($this->container['bar']));
    }

    public function testNotFoundExceptionImplementation()
    {
        $this->expectException(EntryNotFoundException::class);
        $this->container->get('unknown');
    }

    public function testBindIfNotBound()
    {
        $this->container->bindIf('foo', function () {
            return 'bar';
        });

        $this->assertEquals('bar', $this->container->get('foo'));

        $this->container->bindIf('foo', function () {
            return 'baz';
        });

        // Should still return 'bar' since it was already bound
        $this->assertEquals('bar', $this->container->get('foo'));
    }

    public function testContextualBinding()
    {
        $this->container->bind(ClassWithDependency::class);

        // Normal resolution
        $instance1 = $this->container->make(ClassWithDependency::class);
        $this->assertInstanceOf(SimpleClass::class, $instance1->dependency);

        // Contextual resolution
        $mock = $this->createMock(SimpleClass::class);
        $this->container->when(ClassWithDependency::class)
            ->needs(SimpleClass::class)
            ->give(function() use ($mock) {
                return $mock;
            });

        $instance2 = $this->container->make(ClassWithDependency::class);
        $this->assertSame($mock, $instance2->dependency);
    }

    public function testTagging()
    {
        $this->container->bind('foo', function () {
            return 'foo-value';
        });

        $this->container->bind('bar', function () {
            return 'bar-value';
        });

        $this->container->tag(['foo', 'bar'], 'test-tag');

        $tagged = $this->container->tagged('test-tag');
        $values = iterator_to_array($tagged);

        $this->assertCount(2, $values);
        $this->assertEquals('foo-value', $values[0]);
        $this->assertEquals('bar-value', $values[1]);
    }

    public function testResolvingCallbacks()
    {
        $called = false;

        $this->container->resolving('foo', function ($value, $container) use (&$called) {
            $called = true;
            $this->assertEquals('bar', $value);
            $this->assertSame($this->container, $container);
        });

        $this->container->bind('foo', function () {
            return 'bar';
        });

        $this->container->make('foo');

        $this->assertTrue($called);
    }

    public function testMakeWithParameters()
    {
        $instance = $this->container->make(ClassWithParam::class, ['param' => 'custom-value']);
        $this->assertEquals('custom-value', $instance->param);
    }

    public function testExtendInstance()
    {
        $this->container->bind('foo', function () {
            $object = new stdClass();
            $object->original = true;
            return $object;
        });

        $this->container->extend('foo', function ($object, $container) {
            $object->extended = true;
            return $object;
        });

        $instance = $this->container->make('foo');

        $this->assertTrue($instance->original);
        $this->assertTrue($instance->extended);
    }

    public function testUnresolvablePrimitive()
    {
        $this->expectException(BindingResolutionException::class);
        $this->container->make(ClassWithPrimitive::class);
    }

    // TODO: This hangs in a loop

//    public function testCircularDependencyException()
//    {
//        $this->expectException(CircularDependencyException::class);
//
//        // Register circular dependencies
//        $this->container->bind(CircularClass1::class, function ($container) {
//            return new CircularClass1($container->make(CircularClass2::class));
//        });
//
//        $this->container->bind(CircularClass2::class, function ($container) {
//            return new CircularClass2($container->make(CircularClass1::class));
//        });
//
//        // This should throw a CircularDependencyException
//        $this->container->make(CircularClass1::class);
//    }
}

// Test classes for dependency injection scenarios
class SimpleClass
{
}

class ClassWithDependency
{
    public $dependency;

    public function __construct(SimpleClass $dependency)
    {
        $this->dependency = $dependency;
    }
}

class ClassWithParam
{
    public $param;

    public function __construct($param = 'default')
    {
        $this->param = $param;
    }
}

class ClassWithPrimitive
{
    public function __construct(string $unresolvable)
    {
        // This parameter can't be resolved automatically
    }
}

// Classes for circular dependency testing
class CircularClass1
{
    public function __construct(CircularClass2 $dependency)
    {
    }
}

class CircularClass2
{
    public function __construct(CircularClass1 $dependency)
    {
    }
}