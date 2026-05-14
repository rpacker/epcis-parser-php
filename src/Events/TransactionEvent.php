<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use BackedEnum;
use DOMDocument;
use DOMElement;

class TransactionEvent extends EPCISBusinessEvent
{
    /**
     * @param string[]          $epcList
     * @param QuantityElement[] $quantityList
     */
    public function __construct(
        public string $eventTime = '',
        public string $eventTimezoneOffset = '+00:00',
        public ?string $recordTime = null,
        public ?string $eventId = null,
        public ?ErrorDeclaration $errorDeclaration = null,
        public array $sensorElementList = [],
        public Action|string $action = Action::Add,
        public null|BackedEnum|string $bizStep = null,
        public null|BackedEnum|string $disposition = null,
        public ?string $readPoint = null,
        public ?string $bizLocation = null,
        public array $sourceList = [],
        public array $destinationList = [],
        public array $businessTransactionList = [],
        public ?PersistentDisposition $persistentDisposition = null,
        public ?string $parentId = null,
        public array $epcList = [],
        public array $quantityList = [],
    ) {
        parent::__construct(
            $eventTime, $eventTimezoneOffset, $recordTime, $eventId,
            $errorDeclaration, $sensorElementList, $action, $bizStep, $disposition,
            $readPoint, $bizLocation, $sourceList, $destinationList,
            $businessTransactionList, $persistentDisposition,
        );
    }

    public function renderFragment(DOMDocument $doc, DOMElement $parent): DOMElement
    {
        $el = $doc->createElement('TransactionEvent');
        $this->appendBaseFields($doc, $el);

        // bizTransactionList comes before epcList in EPCIS 2.0 TransactionEvent
        if (!empty($this->businessTransactionList)) {
            $btl = $doc->createElement('bizTransactionList');
            foreach ($this->businessTransactionList as $bt) {
                $btEl = $doc->createElement('bizTransaction', $bt->bizTransaction);
                $btEl->setAttribute('type', $bt->type);
                $btl->appendChild($btEl);
            }
            $el->appendChild($btl);
        }

        if ($this->parentId !== null) {
            $el->appendChild($doc->createElement('parentID', $this->parentId));
        }

        if (!empty($this->epcList)) {
            $list = $doc->createElement('epcList');
            foreach ($this->epcList as $epc) {
                $list->appendChild($doc->createElement('epc', $epc));
            }
            $el->appendChild($list);
        }

        $action = $this->action instanceof Action ? $this->action->value : $this->action;
        $el->appendChild($doc->createElement('action', $action));
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

        if (!empty($this->quantityList)) {
            $ql = $doc->createElement('quantityList');
            foreach ($this->quantityList as $qe) {
                $qEl = $doc->createElement('quantityElement');
                $qEl->appendChild($doc->createElement('epcClass', $qe->epcClass));
                $qEl->appendChild($doc->createElement('quantity', (string) $qe->quantity));
                if ($qe->uom !== null) $qEl->appendChild($doc->createElement('uom', $qe->uom));
                $ql->appendChild($qEl);
            }
            $el->appendChild($ql);
        }

        $this->appendSourceDestination($doc, $el);
        $this->appendSensorElementList($doc, $el);
        $this->appendPersistentDisposition($doc, $el);

        $parent->appendChild($el);
        return $el;
    }

    public function renderJson(): array
    {
        $d = ['eventTime' => $this->eventTime, 'eventTimeZoneOffset' => $this->eventTimezoneOffset];
        if (!empty($this->businessTransactionList)) {
            $d['bizTransactionList'] = array_map(
                fn($bt) => ['type' => $bt->type, 'bizTransaction' => $bt->bizTransaction],
                $this->businessTransactionList
            );
        }
        if ($this->parentId !== null) $d['parentID'] = $this->parentId;
        if (!empty($this->epcList)) $d['epcList'] = $this->epcList;
        $d['action'] = $this->action instanceof Action ? $this->action->value : $this->action;
        if ($this->bizStep !== null)    $d['bizStep']    = $this->resolveEnum($this->bizStep);
        if ($this->disposition !== null) $d['disposition'] = $this->resolveEnum($this->disposition);
        if ($this->readPoint !== null)  $d['readPoint']  = ['id' => $this->readPoint];
        if ($this->bizLocation !== null) $d['bizLocation'] = ['id' => $this->bizLocation];
        if (!empty($this->quantityList)) {
            $d['quantityList'] = array_map(function ($qe) {
                $item = ['epcClass' => $qe->epcClass, 'quantity' => $qe->quantity];
                if ($qe->uom !== null) $item['uom'] = $qe->uom;
                return $item;
            }, $this->quantityList);
        }
        if (!empty($this->sourceList)) {
            $d['sourceList'] = array_map(fn($s) => ['type' => $s->type, 'source' => $s->source], $this->sourceList);
        }
        if (!empty($this->destinationList)) {
            $d['destinationList'] = array_map(fn($d2) => ['type' => $d2->type, 'destination' => $d2->destination], $this->destinationList);
        }
        return ['transactionEvent' => $d];
    }
}
