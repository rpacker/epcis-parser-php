<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use BackedEnum;
use DOMDocument;
use DOMElement;

class TransformationEvent extends EPCISEvent
{
    /**
     * @param string[]                         $inputEpcList
     * @param QuantityElement[]                $inputQuantityList
     * @param string[]                         $outputEpcList
     * @param QuantityElement[]                $outputQuantityList
     * @param BusinessTransaction[]            $businessTransactionList
     * @param Source[]                         $sourceList
     * @param Destination[]                    $destinationList
     * @param InstanceLotMasterDataAttribute[] $ilmd
     * @param SensorElement[]                  $sensorElementList
     */
    public function __construct(
        public string $eventTime = '',
        public string $eventTimezoneOffset = '+00:00',
        public ?string $recordTime = null,
        public ?string $eventId = null,
        public ?ErrorDeclaration $errorDeclaration = null,
        public array $sensorElementList = [],
        public array $inputEpcList = [],
        public array $inputQuantityList = [],
        public array $outputEpcList = [],
        public array $outputQuantityList = [],
        public ?string $transformationId = null,
        public null|BackedEnum|string $bizStep = null,
        public null|BackedEnum|string $disposition = null,
        public ?string $readPoint = null,
        public ?string $bizLocation = null,
        public array $businessTransactionList = [],
        public array $sourceList = [],
        public array $destinationList = [],
        public array $ilmd = [],
        public ?PersistentDisposition $persistentDisposition = null,
    ) {
        parent::__construct($eventTime, $eventTimezoneOffset, $recordTime, $eventId, $errorDeclaration, $sensorElementList);
    }

    public function renderFragment(DOMDocument $doc, DOMElement $parent): DOMElement
    {
        $el = $doc->createElement('TransformationEvent');
        $this->appendBaseFields($doc, $el);

        if (!empty($this->inputEpcList)) {
            $list = $doc->createElement('inputEPCList');
            foreach ($this->inputEpcList as $epc) {
                $list->appendChild($doc->createElement('epc', $epc));
            }
            $el->appendChild($list);
        }
        if (!empty($this->inputQuantityList)) {
            $el->appendChild($this->buildQuantityList($doc, 'inputQuantityList', $this->inputQuantityList));
        }
        if (!empty($this->outputEpcList)) {
            $list = $doc->createElement('outputEPCList');
            foreach ($this->outputEpcList as $epc) {
                $list->appendChild($doc->createElement('epc', $epc));
            }
            $el->appendChild($list);
        }
        if (!empty($this->outputQuantityList)) {
            $el->appendChild($this->buildQuantityList($doc, 'outputQuantityList', $this->outputQuantityList));
        }
        if ($this->transformationId !== null) {
            $el->appendChild($doc->createElement('transformationID', $this->transformationId));
        }
        if ($this->bizStep !== null) {
            $el->appendChild($doc->createElement('bizStep', $this->resolveEnum($this->bizStep)));
        }
        if ($this->disposition !== null) {
            $el->appendChild($doc->createElement('disposition', $this->resolveEnum($this->disposition)));
        }
        if ($this->readPoint !== null) {
            $rp = $doc->createElement('readPoint');
            $rp->appendChild($doc->createElement('id', $this->readPoint));
            $el->appendChild($rp);
        }
        if ($this->bizLocation !== null) {
            $bl = $doc->createElement('bizLocation');
            $bl->appendChild($doc->createElement('id', $this->bizLocation));
            $el->appendChild($bl);
        }
        if (!empty($this->businessTransactionList)) {
            $btl = $doc->createElement('bizTransactionList');
            foreach ($this->businessTransactionList as $bt) {
                $btEl = $doc->createElement('bizTransaction', $bt->bizTransaction);
                $btEl->setAttribute('type', $bt->type);
                $btl->appendChild($btEl);
            }
            $el->appendChild($btl);
        }
        if (!empty($this->sourceList)) {
            $sl = $doc->createElement('sourceList');
            foreach ($this->sourceList as $s) {
                $sEl = $doc->createElement('source', $s->source);
                $sEl->setAttribute('type', $s->type);
                $sl->appendChild($sEl);
            }
            $el->appendChild($sl);
        }
        if (!empty($this->destinationList)) {
            $dl = $doc->createElement('destinationList');
            foreach ($this->destinationList as $d) {
                $dEl = $doc->createElement('destination', $d->destination);
                $dEl->setAttribute('type', $d->type);
                $dl->appendChild($dEl);
            }
            $el->appendChild($dl);
        }
        $this->appendSensorElementList($doc, $el);
        if ($this->persistentDisposition !== null && !$this->persistentDisposition->isEmpty()) {
            $pd = $doc->createElement('persistentDisposition');
            foreach ($this->persistentDisposition->unset as $v) {
                $pd->appendChild($doc->createElement('unset', $v instanceof BackedEnum ? $v->value : $v));
            }
            foreach ($this->persistentDisposition->set as $v) {
                $pd->appendChild($doc->createElement('set', $v instanceof BackedEnum ? $v->value : $v));
            }
            $el->appendChild($pd);
        }
        if (!empty($this->ilmd)) {
            $ilmd = $doc->createElement('ilmd');
            $hasCbv = array_filter($this->ilmd, fn($a) => $a->isCbv);
            if ($hasCbv) {
                $ilmd->setAttribute('xmlns:cbvmda', 'urn:epcglobal:cbv:mda');
            }
            foreach ($this->ilmd as $attr) {
                $node = $doc->createElement(
                    $attr->isCbv ? 'cbvmda:' . $attr->localName : $attr->localName,
                    $attr->value,
                );
                $ilmd->appendChild($node);
            }
            $el->appendChild($ilmd);
        }

        $parent->appendChild($el);
        return $el;
    }

    public function renderJson(): array
    {
        $d = ['eventTime' => $this->eventTime, 'eventTimeZoneOffset' => $this->eventTimezoneOffset];
        if (!empty($this->inputEpcList))    $d['inputEPCList']    = $this->inputEpcList;
        if (!empty($this->outputEpcList))   $d['outputEPCList']   = $this->outputEpcList;
        if ($this->transformationId !== null) $d['transformationID'] = $this->transformationId;
        if ($this->bizStep !== null)         $d['bizStep']         = $this->resolveEnum($this->bizStep);
        if ($this->disposition !== null)     $d['disposition']     = $this->resolveEnum($this->disposition);
        if ($this->readPoint !== null)       $d['readPoint']       = ['id' => $this->readPoint];
        if ($this->bizLocation !== null)     $d['bizLocation']     = ['id' => $this->bizLocation];
        return ['transformationEvent' => $d];
    }

    private function buildQuantityList(DOMDocument $doc, string $tag, array $items): DOMElement
    {
        $list = $doc->createElement($tag);
        foreach ($items as $qe) {
            $qEl = $doc->createElement('quantityElement');
            $qEl->appendChild($doc->createElement('epcClass', $qe->epcClass));
            $qEl->appendChild($doc->createElement('quantity', (string) $qe->quantity));
            if ($qe->uom !== null) $qEl->appendChild($doc->createElement('uom', $qe->uom));
            $list->appendChild($qEl);
        }
        return $list;
    }

    private function resolveEnum(BackedEnum|string|null $value): ?string
    {
        if ($value === null) return null;
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
