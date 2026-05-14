<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

class PersistentDisposition
{
    /**
     * @param string[] $set   Disposition URNs that have been set
     * @param string[] $unset Disposition URNs that have been unset
     */
    public function __construct(
        public readonly array $set = [],
        public readonly array $unset = [],
    ) {}

    public function isEmpty(): bool
    {
        return empty($this->set) && empty($this->unset);
    }
}
