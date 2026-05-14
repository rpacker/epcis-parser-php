<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use BackedEnum;

class BusinessTransaction
{
    public function __construct(
        public readonly string $type,
        public readonly string $bizTransaction,
    ) {}

    public static function fromEnum(BackedEnum $type, string $bizTransaction): self
    {
        return new self((string) $type->value, $bizTransaction);
    }
}
