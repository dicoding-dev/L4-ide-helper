<?php

use Barryvdh\LaravelIdeHelper\Method;
use PHPUnit\Framework\TestCase;

class MethodTest extends TestCase
{
    /**
     * Test that we can actually instantiate the class
     */
    public function testCanInstantiate()
    {
        $reflectionClass = new \ReflectionClass(ExampleClass::class);
        $reflectionMethod = $reflectionClass->getMethod('setName');

        $method = new Method($reflectionMethod, 'Example', $reflectionClass);

        static::assertInstanceOf(Method::class, $method);
    }

    /**
     * Test the output of a class
     */
    public function testOutput()
    {
        $reflectionClass = new \ReflectionClass(ExampleClass::class);
        $reflectionMethod = $reflectionClass->getMethod('setName');

        $method = new Method($reflectionMethod, 'Example', $reflectionClass);

        $output = '/**
 * 
 *
 * @param string $last 
 * @param string $first 
 */';
        static::assertEquals($output, $method->getDocComment(''));
        static::assertEquals('setName', $method->getName());
        static::assertEquals('\\'.ExampleClass::class, $method->getDeclaringClass());
        static::assertEquals('$last, $first', $method->getParams(true));
        static::assertEquals(['$last', '$first'], $method->getParams(false));
        static::assertEquals('$last, $first = \'Barry\'', $method->getParamsWithDefault(true));
        static::assertEquals(['$last', '$first = \'Barry\''], $method->getParamsWithDefault(false));
        static::assertEquals(true, $method->shouldReturn());
    }
}

class ExampleClass
{
    /**
     * @param string $last
     * @param string $first
     */
    public function setName($last, $first = 'Barry')
    {
        return;
    }
}