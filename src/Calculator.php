<?php
declare(strict_types=1);

namespace Composer;

use RuntimeException;

class Calculator
{

    public function add($a, $b): float|int {
        return $a + $b;
    }

    public function subtract($a, $b): float|int {
        return $a - $b;
    }

    public function multiply($a, $b): float|int
    {
        return $a * $b;
    }

    public function divide($a, $b): float|int
    {
        if ($b === 0) {
            throw new RuntimeException('Division by zero is not allowed.');
        }
        return $a / $b;
    }
}
