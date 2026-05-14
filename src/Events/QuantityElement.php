<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

class QuantityElement
{
    public function __construct(
        public readonly string $epcClass,
        public readonly float $quantity,
        public readonly ?string $uom = null,
    ) {}
}
