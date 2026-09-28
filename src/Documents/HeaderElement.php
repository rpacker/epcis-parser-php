<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Documents;

/**
 * An industry-extension element appended to <EPCISHeader> after the SBDH
 * and master data (the header's xsd:any slot), e.g. the GS1 US healthcare
 * DSCSA transaction statement.
 */
interface HeaderElement
{
    public function render(\DOMDocument $doc): \DOMElement;

    /**
     * Namespaces to declare on the document root.
     *
     * @return array<string, string> prefix => URI
     */
    public function namespaces(): array;
}
