<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Helpers;

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
            throw new \InvalidArgumentException("companyPrefix + indicator + itemReference must total 13 digits, got {$total}");
        }

        return "urn:epc:id:sgtin:{$companyPrefix}.{$indicator}{$itemReference}." . self::escapeUrnComponent($serial);
    }

    /**
     * Build a SGTIN URN from a GTIN-14 and serial number.
     * GTIN-14: indicator(1) + company+item(12) + check(1)
     * The company prefix length determines the split point and must be provided.
     */
    public static function gtin14ToSgtinUrn(string $gtin14, string $serial, int $companyPrefixLength): string
    {
        if (strlen($gtin14) !== 14) {
            throw new \InvalidArgumentException('GTIN-14 must be 14 digits, got ' . strlen($gtin14));
        }
        $indicator     = $gtin14[0];
        $companyPrefix = substr($gtin14, 1, $companyPrefixLength);
        $itemReference = substr($gtin14, 1 + $companyPrefixLength, 12 - $companyPrefixLength);

        return "urn:epc:id:sgtin:{$companyPrefix}.{$indicator}{$itemReference}." . self::escapeUrnComponent($serial);
    }

    /**
     * urn:epc:idpat:sgtin:PREFIX.ITEMREF.* — the EPCClass master data id
     * covering every serial of a GTIN.
     */
    public static function gtin14ToSgtinPattern(string $gtin14, int $companyPrefixLength): string
    {
        $urn = self::gtin14ToSgtinUrn($gtin14, '0', $companyPrefixLength);

        return 'urn:epc:idpat:sgtin:' . substr($urn, strlen('urn:epc:id:sgtin:'), -2) . '.*';
    }

    /**
     * urn:epc:id:sscc:PREFIX.EXTENSION+SERIALREF from a formatted SSCC-18
     * (extension digit, company prefix, serial reference, check digit).
     */
    public static function sscc18ToSsccUrn(string $sscc18, int $companyPrefixLength): string
    {
        if (! preg_match('/^\d{18}$/', $sscc18)) {
            throw new \InvalidArgumentException('SSCC must be 18 digits, got "' . $sscc18 . '"');
        }
        if ($companyPrefixLength < 6 || $companyPrefixLength > 12) {
            throw new \InvalidArgumentException("Company prefix length must be 6-12, got {$companyPrefixLength}");
        }
        $companyPrefix = substr($sscc18, 1, $companyPrefixLength);
        $serialRef     = $sscc18[0] . substr($sscc18, 1 + $companyPrefixLength, 16 - $companyPrefixLength);

        return "urn:epc:id:sscc:{$companyPrefix}.{$serialRef}";
    }

    /**
     * GS1 mod-10 check digit over $digits (the identifier without its check digit).
     */
    public static function checkDigit(string $digits): int
    {
        if (! preg_match('/^\d+$/', $digits)) {
            throw new \InvalidArgumentException('Check digit input must be digits only');
        }
        $sum    = 0;
        $weight = 3;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $sum += (int) $digits[$i] * $weight;
            $weight = $weight === 3 ? 1 : 3;
        }

        return (10 - $sum % 10) % 10;
    }

    /**
     * A GTIN-12/13/14 left-padded to 14 digits, or null when it isn't one —
     * wrong length, non-digits, or a check digit that doesn't match (a
     * mistyped GTIN would otherwise become a well-formed URN for some
     * other product).
     */
    public static function normalizeGtin14(string $gtin): ?string
    {
        if (! preg_match('/^\d{12,14}$/', $gtin)) {
            return null;
        }
        $gtin = str_pad($gtin, 14, '0', STR_PAD_LEFT);

        return self::checkDigit(substr($gtin, 0, 13)) === (int) $gtin[13] ? $gtin : null;
    }

    public static function isValidSscc18(string $sscc): bool
    {
        return preg_match('/^\d{18}$/', $sscc) === 1
            && self::checkDigit(substr($sscc, 0, 17)) === (int) $sscc[17];
    }

    /**
     * The GS1 company prefix of an SGLN URN (urn:epc:id:sgln:PREFIX.LOCREF.EXT).
     */
    public static function sglnCompanyPrefix(string $sgln): ?string
    {
        return preg_match('/^urn:epc:id:sgln:(\d+)\.(\d*)\.[^.]*$/', $sgln, $m) ? $m[1] : null;
    }

    /**
     * The GLN-13 an SGLN URN identifies: prefix + location reference +
     * check digit. The SGLN's trailing extension (".0") is not part of it.
     */
    public static function sglnToGln13(string $sgln): ?string
    {
        if (! preg_match('/^urn:epc:id:sgln:(\d+)\.(\d*)\.[^.]*$/', $sgln, $m) || strlen($m[1] . $m[2]) !== 12) {
            return null;
        }

        return $m[1] . $m[2] . self::checkDigit($m[1] . $m[2]);
    }

    /**
     * Percent-encodes the characters the GS1 Tag Data Standard reserves in
     * an EPC URN serial component.
     */
    public static function escapeUrnComponent(string $value): string
    {
        return strtr($value, ['%' => '%25', '"' => '%22', '&' => '%26', '/' => '%2F', '<' => '%3C', '>' => '%3E', '?' => '%3F']);
    }

    /**
     * Yield SGTIN URNs for a range of serials.
     *
     * @return \Generator<string>
     */
    public static function gtinUrnGenerator(
        string $companyPrefix,
        string $indicator,
        string $itemReference,
        iterable $serialRange
    ): \Generator {
        foreach ($serialRange as $serial) {
            yield self::gtinToUrn($companyPrefix, $indicator, $itemReference, (string) $serial);
        }
    }

    /**
     * Yield SSCC URNs for a range of serials.
     * companyPrefix + serial padded to 17 digits total.
     *
     * @return \Generator<string>
     */
    public static function ssccUrnGenerator(string $companyPrefix, iterable $serialRange): \Generator
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
     * e.g. ['2026-05-13T12:00:00.000Z', '+00:00'].
     *
     * @return array{0: string, 1: string}
     */
    public static function getCurrentUtcTimeAndOffset(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));

        return [$now->format('Y-m-d\TH:i:s.v\Z'), '+00:00'];
    }
}
