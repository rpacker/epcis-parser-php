<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Helpers;

use Generator;
use InvalidArgumentException;

class EpcHelper
{
    /**
     * Build a SGTIN URN from company prefix, indicator, item reference, and serial.
     * Total digits of companyPrefix + indicator + itemReference must equal 13.
     */
    public static function gtinToUrn(
        string $companyPrefix,
        string $indicator,
        string $itemReference,
        string $serial
    ): string {
        $total = strlen($companyPrefix) + strlen($indicator) + strlen($itemReference);
        if ($total !== 13) {
            throw new InvalidArgumentException(
                "companyPrefix + indicator + itemReference must total 13 digits, got {$total}"
            );
        }
        return "urn:epc:id:sgtin:{$companyPrefix}.{$indicator}{$itemReference}.{$serial}";
    }

    /**
     * Build a SGTIN URN from a GTIN-14 and serial number.
     * GTIN-14: indicator(1) + company+item(12) + check(1)
     * The company prefix length determines the split point and must be provided.
     */
    public static function gtin14ToSgtinUrn(string $gtin14, string $serial, int $companyPrefixLength): string
    {
        if (strlen($gtin14) !== 14) {
            throw new InvalidArgumentException("GTIN-14 must be 14 digits, got " . strlen($gtin14));
        }
        $indicator    = $gtin14[0];
        $companyPrefix = substr($gtin14, 1, $companyPrefixLength);
        $itemReference = substr($gtin14, 1 + $companyPrefixLength, 12 - $companyPrefixLength);
        return "urn:epc:id:sgtin:{$companyPrefix}.{$indicator}{$itemReference}.{$serial}";
    }

    /**
     * Yield SGTIN URNs for a range of serials.
     *
     * @return Generator<string>
     */
    public static function gtinUrnGenerator(
        string $companyPrefix,
        string $indicator,
        string $itemReference,
        iterable $serialRange
    ): Generator {
        foreach ($serialRange as $serial) {
            yield self::gtinToUrn($companyPrefix, $indicator, $itemReference, (string) $serial);
        }
    }

    /**
     * Yield SSCC URNs for a range of serials.
     * companyPrefix + serial padded to 17 digits total.
     *
     * @return Generator<string>
     */
    public static function ssccUrnGenerator(string $companyPrefix, iterable $serialRange): Generator
    {
        $serialLength = 17 - strlen($companyPrefix);
        foreach ($serialRange as $serial) {
            $paddedSerial = str_pad((string) $serial, $serialLength, '0', STR_PAD_LEFT);
            yield "urn:epc:id:sscc:{$companyPrefix}.{$paddedSerial}";
        }
    }

    /**
     * Build a SGLN URN from GLN-13 company prefix, location reference, and extension.
     */
    public static function gln13DataToSglnUrn(
        string $companyPrefix,
        string $locationReference,
        string $extension
    ): string {
        return "urn:epc:id:sgln:{$companyPrefix}.{$locationReference}.{$extension}";
    }

    /**
     * Returns [isoTimestamp, timezoneOffset] for the current UTC time.
     * e.g. ['2026-05-13T12:00:00.000Z', '+00:00']
     *
     * @return array{0: string, 1: string}
     */
    public static function getCurrentUtcTimeAndOffset(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return [$now->format('Y-m-d\TH:i:s.v\Z'), '+00:00'];
    }
}
