<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

class SensorReport
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?float $value = null,
        public readonly ?string $uom = null,
        public readonly ?float $minValue = null,
        public readonly ?float $maxValue = null,
        public readonly ?float $meanValue = null,
        public readonly ?float $sDev = null,
        public readonly ?float $percRank = null,
        public readonly ?float $percValue = null,
        public readonly ?string $chemicalSubstance = null,
        public readonly ?string $microorganism = null,
        public readonly ?string $deviceId = null,
        public readonly ?string $deviceMetadata = null,
        public readonly ?string $rawData = null,
        public readonly ?string $time = null,
        public readonly ?string $component = null,
        public readonly ?string $stringValue = null,
        public readonly ?bool $booleanValue = null,
        public readonly ?string $hexBinaryValue = null,
        public readonly ?string $uriValue = null,
        public readonly ?string $dataProcessingMethod = null,
    ) {}
}
