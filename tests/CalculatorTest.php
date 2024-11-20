<?php

declare(strict_types=1);

namespace Composer\Tests;

use Composer\Calculator;
use PHPUnit\Framework\TestCase;

class CalculatorTest extends TestCase
{

    public function testMultiplyByZeroReturnsZero(): void
    {
        $calculator = new Calculator();
        $this->assertSame(0, $calculator->multiply(5, 0));
        $this->assertSame(0, $calculator->multiply(0, 10));
        $this->assertSame(0, $calculator->multiply(0, 0));
    }

    public function testDivideTwoPositiveIntegers(): void
    {
        $calculator = new Calculator();
        $this->assertSame(2, $calculator->divide(4, 2));
    }
}
