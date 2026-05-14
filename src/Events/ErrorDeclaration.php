<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

class ErrorDeclaration
{
    public function __construct(
        public readonly string $declarationTime,
        public readonly ?string $reason = null,
        public readonly array $correctiveEventIds = [],
    ) {}
}
