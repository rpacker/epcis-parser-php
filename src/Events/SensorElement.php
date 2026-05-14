<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

class SensorElement
{
    /** @param SensorReport[] $reports */
    public function __construct(
        public readonly SensorMetadata $metadata,
        public readonly array $reports = [],
    ) {}
}
