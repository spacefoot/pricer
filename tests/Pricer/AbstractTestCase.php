<?php
namespace Pricer;

use PHPUnit\Framework\TestCase;

class AbstractTestCase extends TestCase
{
    protected function assertPrice(float $expected, ProductPrice $current)
    {
        $this->assertEquals($expected, $current->sellingPrice, 'Price type = '.$current->type, 0.01);
    }
}
