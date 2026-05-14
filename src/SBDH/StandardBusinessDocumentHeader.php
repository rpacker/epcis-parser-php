<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\SBDH;

class StandardBusinessDocumentHeader
{
    /** @param Partner[] $receivers */
    public function __construct(
        public readonly Partner $sender,
        public readonly array $receivers,
        public readonly DocumentIdentification $documentIdentification,
        public readonly string $headerVersion = '1.0',
    ) {}
}
