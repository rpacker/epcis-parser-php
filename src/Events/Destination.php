<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

class Destination
{
    public function __construct(
        public readonly string $type,
        public readonly string $destination,
    ) {}
}
