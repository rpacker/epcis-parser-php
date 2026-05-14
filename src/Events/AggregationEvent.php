<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use BackedEnum;
use DOMDocument;
use DOMElement;

class AggregationEvent extends EPCISBusinessEvent
{
    /**
     * @param string[]          $childEpcs
     * @param QuantityElement[] $childQuantityList
     */
    public function __construct(
        public string $eventTime = '',
        public string $eventTimezoneOffset = '+00:00',
        public ?string $recordTime = null,
        public ?string $eventId = null,
        public ?ErrorDeclaration $errorDeclaration = null,
        public array $sensorElementList = [],
        public Action|string $action = Action::Observe,
        public null|BackedEnum|string $bizStep = null,
        public null|BackedEnum|string $disposition = null,
        public ?string $readPoint = null,
        public ?string $bizLocation = null,
        public array $sourceList = [],
        public array $destinationList = [],
        public array $businessTransactionList = [],
        public ?PersistentDisposition $persistentDisposition = null,
        public ?string $parentId = null,
        public array $childEpcs = [],
        public array $childQuantityList = [],
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
        return $this->buildElement($doc, $parent, 'AggregationEvent');
    }

    protected function buildElement(DOMDocument $doc, DOMElement $parent, string $tag): DOMElement
    {
        $el = $doc->createElement($tag);
        $this->appendBaseFields($doc, $el);

        if ($this->parentId !== null) {
            $el->appendChild($doc->createElement('parentID', $this->parentId));
        }

        $childEpcs = $doc->createElement('childEPCs');
        foreach ($this->childEpcs as $epc) {
            $childEpcs->appendChild($doc->createElement('epc', $epc));
        }
        $el->appendChild($childEpcs);

        $this->appendBusinessFields($doc, $el);

        if (!empty($this->childQuantityList)) {
            $cql = $doc->createElement('childQuantityList');
            foreach ($this->childQuantityList as $qe) {
                $qEl = $doc->createElement('quantityElement');
                $qEl->appendChild($doc->createElement('epcClass', $qe->epcClass));
                $qEl->appendChild($doc->createElement('quantity', (string) $qe->quantity));
                if ($qe->uom !== null) {
                    $qEl->appendChild($doc->createElement('uom', $qe->uom));
                }
                $cql->appendChild($qEl);
            }
            $el->appendChild($cql);
        }

        $this->appendSourceDestination($doc, $el);
        $this->appendSensorElementList($doc, $el);
        $this->appendPersistentDisposition($doc, $el);

        $parent->appendChild($el);
        return $el;
    }

    public function renderJson(): array
    {
        return [$this->jsonKey() => $this->buildJsonData()];
    }

    protected function jsonKey(): string
    {
        return 'aggregationEvent';
    }

    protected function buildJsonData(): array
    {
        $d = ['eventTime' => $this->eventTime, 'eventTimeZoneOffset' => $this->eventTimezoneOffset];
        if ($this->parentId !== null) $d['parentID'] = $this->parentId;
        $d['childEPCs'] = $this->childEpcs;
        $d['action'] = $this->action instanceof Action ? $this->action->value : $this->action;
        if ($this->bizStep !== null)    $d['bizStep']    = $this->resolveEnum($this->bizStep);
        if ($this->disposition !== null) $d['disposition'] = $this->resolveEnum($this->disposition);
        if ($this->readPoint !== null)  $d['readPoint']  = ['id' => $this->readPoint];
        if ($this->bizLocation !== null) $d['bizLocation'] = ['id' => $this->bizLocation];
        if (!empty($this->businessTransactionList)) {
            $d['bizTransactionList'] = array_map(
                fn($bt) => ['type' => $bt->type, 'bizTransaction' => $bt->bizTransaction],
                $this->businessTransactionList
            );
        }
        if (!empty($this->childQuantityList)) {
            $d['childQuantityList'] = array_map(function ($qe) {
                $item = ['epcClass' => $qe->epcClass, 'quantity' => $qe->quantity];
                if ($qe->uom !== null) $item['uom'] = $qe->uom;
                return $item;
            }, $this->childQuantityList);
        }
        if (!empty($this->sourceList)) {
            $d['sourceList'] = array_map(fn($s) => ['type' => $s->type, 'source' => $s->source], $this->sourceList);
        }
        if (!empty($this->destinationList)) {
            $d['destinationList'] = array_map(fn($d2) => ['type' => $d2->type, 'destination' => $d2->destination], $this->destinationList);
        }
        return $d;
    }
}
