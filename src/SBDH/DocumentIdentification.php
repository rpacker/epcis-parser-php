<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\SBDH;

class DocumentIdentification
{
    public function __construct(
        public readonly string $standard = 'EPCglobal',
        public readonly string $typeVersion = '1.0',
        public readonly string $instanceIdentifier = '',
        public readonly string $type = 'Events',
        public readonly ?string $creationDateAndTime = null,
    ) {}
}
