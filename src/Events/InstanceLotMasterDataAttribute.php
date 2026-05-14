<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use BackedEnum;
use Rpacker\EpcisParser\CBV\InstanceLotMasterData;

class InstanceLotMasterDataAttribute
{
    public readonly string $localName;
    public readonly bool $isCbv;

    public function __construct(
        public readonly string|BackedEnum $name,
        public readonly string $value,
    ) {
        if ($name instanceof InstanceLotMasterData) {
            $this->localName = $name->value;
            $this->isCbv = true;
        } else {
            $this->localName = is_string($name) ? $name : $name->value;
            $this->isCbv = false;
        }
    }
}
