<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

class SensorMetadata
{
    public function __construct(
        public readonly ?string $time = null,
        public readonly ?string $deviceId = null,
        public readonly ?string $deviceMetadata = null,
        public readonly ?string $rawData = null,
        public readonly ?string $startTime = null,
        public readonly ?string $endTime = null,
        public readonly ?string $bizRules = null,
        public readonly ?string $dataProcessingMethod = null,
    ) {}
}
