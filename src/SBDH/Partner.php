<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\SBDH;

class Partner
{
    public function __construct(
        public readonly string $partnerType,
        public readonly string $identifier,
        public readonly ?string $authority = null,
    ) {}
}
