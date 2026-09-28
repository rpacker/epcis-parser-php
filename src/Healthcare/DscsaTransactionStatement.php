<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Healthcare;

use Rpacker\EpcisParser\Documents\HeaderElement;

/**
 * GS1 US Healthcare (DSCSA) transaction statement:
 * <gs1ushc:dscsaTransactionStatement> in the EPCIS header.
 */
class DscsaTransactionStatement implements HeaderElement
{
    public const NS = 'http://epcis.gs1us.org/hc/ns';

    public function __construct(
        public readonly string $legalNotice,
        public readonly bool $affirmTransactionStatement = true,
    ) {
    }

    public function render(\DOMDocument $doc): \DOMElement
    {
        $el = $doc->createElementNS(self::NS, 'gs1ushc:dscsaTransactionStatement');

        $affirm = $doc->createElementNS(self::NS, 'gs1ushc:affirmTransactionStatement');
        $affirm->appendChild($doc->createTextNode($this->affirmTransactionStatement ? 'true' : 'false'));
        $el->appendChild($affirm);

        $notice = $doc->createElementNS(self::NS, 'gs1ushc:legalNotice');
        $notice->appendChild($doc->createTextNode($this->legalNotice));
        $el->appendChild($notice);

        return $el;
    }

    public function namespaces(): array
    {
        return ['gs1ushc' => self::NS];
    }
}
