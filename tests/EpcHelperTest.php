<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

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
        $this->expectException(\InvalidArgumentException::class);
        EpcHelper::gtinToUrn('030003', '0', '0293', '100'); // total = 6+1+4 = 11, not 13
    }

    public function testGtin14ToSgtinUrn(): void
    {
        // GTIN-14: 00300030293282
        // indicator=0, companyPrefix=030003 (length 6), itemRef=029328, check=2
        $urn = EpcHelper::gtin14ToSgtinUrn('00300030293282', '100011869390', 6);
        $this->assertEquals('urn:epc:id:sgtin:030003.0029328.100011869390', $urn);
    }

    public function testGtin14ToSgtinUrnWithSevenDigitPrefix(): void
    {
        // Apotex: same "003..." shape as above, but a 7-digit prefix (NDC labeler 60505).
        $urn = EpcHelper::gtin14ToSgtinUrn('00360505613267', '589917', 7);
        $this->assertEquals('urn:epc:id:sgtin:0360505.061326.589917', $urn);
    }

    public function testSerialReservedCharactersArePercentEncoded(): void
    {
        $this->assertEquals(
            'urn:epc:id:sgtin:030003.0029328.A%2FB%26C%25',
            EpcHelper::gtin14ToSgtinUrn('00300030293282', 'A/B&C%', 6),
        );
        $this->assertEquals(
            'urn:epc:id:sgtin:030003.0029328.X%3F',
            EpcHelper::gtinToUrn('030003', '0', '029328', 'X?'),
        );
    }

    public function testGtin14ToSgtinPattern(): void
    {
        $this->assertEquals('urn:epc:idpat:sgtin:0360505.061326.*', EpcHelper::gtin14ToSgtinPattern('00360505613267', 7));
    }

    public function testCheckDigit(): void
    {
        $this->assertSame(2, EpcHelper::checkDigit('0030003029328'));
        $this->assertSame(8, EpcHelper::checkDigit('009629500292'));
    }

    public function testNormalizeGtin14(): void
    {
        $this->assertSame('00300030293282', EpcHelper::normalizeGtin14('00300030293282'));
        $this->assertSame('00300030293282', EpcHelper::normalizeGtin14('300030293282'), 'GTIN-12 is left-padded');
        $this->assertNull(EpcHelper::normalizeGtin14('00300030293283'), 'wrong check digit');
        $this->assertNull(EpcHelper::normalizeGtin14('0030003029328X'));
        $this->assertNull(EpcHelper::normalizeGtin14('12345'));
    }

    public function testSscc18(): void
    {
        $this->assertTrue(EpcHelper::isValidSscc18('003605050000001231'));
        $this->assertFalse(EpcHelper::isValidSscc18('003605050000001232'));
        $this->assertEquals('urn:epc:id:sscc:0360505.0000000123', EpcHelper::sscc18ToSsccUrn('003605050000001231', 7));
    }

    public function testSsccPrefixLengthIsValidated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        EpcHelper::sscc18ToSsccUrn('003605050000001231', 13);
    }

    public function testSglnHelpers(): void
    {
        $this->assertSame('0096295', EpcHelper::sglnCompanyPrefix('urn:epc:id:sgln:0096295.00292.0'));
        // GLN carries a check digit, not the SGLN's ".0" extension.
        $this->assertSame('0096295002928', EpcHelper::sglnToGln13('urn:epc:id:sgln:0096295.00292.0'));
        $this->assertSame('1200144187912', EpcHelper::sglnToGln13('urn:epc:id:sgln:120014418791..0'));
        $this->assertNull(EpcHelper::sglnToGln13('urn:epc:id:sgln:0096295.0029.0'));
        $this->assertNull(EpcHelper::sglnCompanyPrefix('not-an-sgln'));
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
