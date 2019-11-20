<?php

namespace DDTrace\Tests\Unit\Util;

use DDTrace\Util\HexConversion;
use PHPUnit\Framework\TestCase;

final class HexConversionTest extends TestCase
{
    public function testIdToHex()
    {
        $id = 9223372036854775807;
        $hex = HexConversion::idToHex($id);
        $this->assertSame("7fffffffffffffff", $hex);
    }

    public function testHexToIntMin()
    {
        $int = HexConversion::hexToInt("0");
        $this->assertSame($int, 0);
    }

    public function testHexToIntMax()
    {
        $int = HexConversion::hexToInt("7fffffffffffffff");
        $this->assertSame(9223372036854775807, $int);
    }

    public function testHexToIntOverMaxPlusOne()
    {
        $int = HexConversion::hexToInt("8000000000000000");
        $this->assertSame(4611686018427387904, $int);
    }

    public function testHexToIntOverMax()
    {
        $int = HexConversion::hexToInt("ffffffffffffffff");
        $this->assertSame(9223372036854775807, $int);
    }
}