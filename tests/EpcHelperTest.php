<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rpacker\EpcisParser\Helpers\EpcHelper;

class EpcHelperTest extends TestCase
{
    public function testGtinToUrn(): void
    {
        $urn = EpcHelper::gtinToUrn('030003', '0', '029328', '100011869390');
        $this->assertEquals('urn:epc:id:sgtin:030003.0029328.100011869390', $urn);
    }

    public function testGtinToUrnValidatesDigitCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        EpcHelper::gtinToUrn('030003', '0', '0293', '100'); // total = 6+1+4 = 11, not 13
    }

    public function testGtin14ToSgtinUrn(): void
    {
        // GTIN-14: 00300030293282
        // indicator=0, companyPrefix=030003 (length 6), itemRef=029328, check=2
        $urn = EpcHelper::gtin14ToSgtinUrn('00300030293282', '100011869390', 6);
        $this->assertEquals('urn:epc:id:sgtin:030003.0029328.100011869390', $urn);
    }

    public function testGtinUrnGenerator(): void
    {
        $urns = iterator_to_array(
            EpcHelper::gtinUrnGenerator('030003', '0', '029328', [100, 101, 102])
        );
        $this->assertCount(3, $urns);
        $this->assertEquals('urn:epc:id:sgtin:030003.0029328.100', $urns[0]);
        $this->assertEquals('urn:epc:id:sgtin:030003.0029328.102', $urns[2]);
    }

    public function testSsccUrnGenerator(): void
    {
        $urns = iterator_to_array(
            EpcHelper::ssccUrnGenerator('0614141', [1234567890, 1234567891])
        );
        $this->assertCount(2, $urns);
        // companyPrefix=7 digits, serial padded to 17-7=10 digits
        $this->assertEquals('urn:epc:id:sscc:0614141.1234567890', $urns[0]);
        $this->assertEquals('urn:epc:id:sscc:0614141.1234567891', $urns[1]);
    }

    public function testGln13DataToSglnUrn(): void
    {
        $urn = EpcHelper::gln13DataToSglnUrn('030003', '000005', '0');
        $this->assertEquals('urn:epc:id:sgln:030003.000005.0', $urn);
    }

    public function testGetCurrentUtcTimeAndOffset(): void
    {
        [$time, $offset] = EpcHelper::getCurrentUtcTimeAndOffset();
        $this->assertEquals('+00:00', $offset);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+Z$/', $time);
    }
}
