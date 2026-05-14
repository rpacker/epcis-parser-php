<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use BackedEnum;
use DOMDocument;
use DOMElement;
use Rpacker\EpcisParser\CBV\InstanceLotMasterData;

class ObjectEvent extends EPCISBusinessEvent
{
    /**
     * @param string[]                          $epcList
     * @param QuantityElement[]                 $quantityList
     * @param InstanceLotMasterDataAttribute[]  $ilmd
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
        public array $epcList = [],
        public array $quantityList = [],
        public array $ilmd = [],
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
        $el = $doc->createElement('ObjectEvent');
        $this->appendBaseFields($doc, $el);

        if (!empty($this->epcList)) {
            $list = $doc->createElement('epcList');
            foreach ($this->epcList as $epc) {
                $list->appendChild($doc->createElement('epc', $epc));
            }
            $el->appendChild($list);
        }

        $this->appendBusinessFields($doc, $el);

        if (!empty($this->quantityList)) {
            $el->appendChild($this->buildQuantityList($doc, 'quantityList', $this->quantityList));
        }

        $this->appendSourceDestination($doc, $el);
        $this->appendSensorElementList($doc, $el);
        $this->appendPersistentDisposition($doc, $el);

        if (!empty($this->ilmd)) {
            $el->appendChild($this->buildIlmd($doc));
        }

        $parent->appendChild($el);
        return $el;
    }

    public function renderJson(): array
    {
        $data = $this->baseJson();
        if (!empty($this->epcList)) {
            $data['epcList'] = $this->epcList;
        }
        $data['action'] = $this->action instanceof Action ? $this->action->value : $this->action;
        if ($this->bizStep !== null) $data['bizStep'] = $this->resolveEnum($this->bizStep);
        if ($this->disposition !== null) $data['disposition'] = $this->resolveEnum($this->disposition);
        if ($this->readPoint !== null) $data['readPoint'] = ['id' => $this->readPoint];
        if ($this->bizLocation !== null) $data['bizLocation'] = ['id' => $this->bizLocation];
        if (!empty($this->businessTransactionList)) {
            $data['bizTransactionList'] = array_map(
                fn($bt) => ['type' => $bt->type, 'bizTransaction' => $bt->bizTransaction],
                $this->businessTransactionList
            );
        }
        if (!empty($this->quantityList)) {
            $data['quantityList'] = $this->quantityListJson($this->quantityList);
        }
        if (!empty($this->sourceList)) {
            $data['sourceList'] = array_map(fn($s) => ['type' => $s->type, 'source' => $s->source], $this->sourceList);
        }
        if (!empty($this->destinationList)) {
            $data['destinationList'] = array_map(fn($d) => ['type' => $d->type, 'destination' => $d->destination], $this->destinationList);
        }
        if (!empty($this->sensorElementList)) {
            $data['sensorElementList'] = $this->sensorElementListJson();
        }
        if ($this->persistentDisposition !== null && !$this->persistentDisposition->isEmpty()) {
            $data['persistentDisposition'] = $this->persistentDispositionJson();
        }
        if (!empty($this->ilmd)) {
            $data['ilmd'] = $this->ilmdJson();
        }
        return ['objectEvent' => $data];
    }

    private function baseJson(): array
    {
        $d = ['eventTime' => $this->eventTime, 'eventTimeZoneOffset' => $this->eventTimezoneOffset];
        if ($this->recordTime !== null) $d['recordTime'] = $this->recordTime;
        if ($this->eventId !== null) $d['eventID'] = $this->eventId;
        return $d;
    }

    private function buildIlmd(DOMDocument $doc): DOMElement
    {
        $ilmd = $doc->createElement('ilmd');
        $hasCbv = array_filter($this->ilmd, fn($a) => $a->isCbv);
        if ($hasCbv) {
            // Declare namespace on <ilmd> so child elements don't each re-declare it
            $ilmd->setAttribute('xmlns:cbvmda', 'urn:epcglobal:cbv:mda');
        }
        foreach ($this->ilmd as $attr) {
            $node = $doc->createElement(
                $attr->isCbv ? 'cbvmda:' . $attr->localName : $attr->localName,
                $attr->value,
            );
            $ilmd->appendChild($node);
        }
        return $ilmd;
    }

    protected function buildQuantityList(DOMDocument $doc, string $tag, array $items): DOMElement
    {
        $list = $doc->createElement($tag);
        foreach ($items as $qe) {
            $qEl = $doc->createElement('quantityElement');
            $qEl->appendChild($doc->createElement('epcClass', $qe->epcClass));
            $qEl->appendChild($doc->createElement('quantity', (string) $qe->quantity));
            if ($qe->uom !== null) {
                $qEl->appendChild($doc->createElement('uom', $qe->uom));
            }
            $list->appendChild($qEl);
        }
        return $list;
    }

    private function quantityListJson(array $items): array
    {
        return array_map(function ($qe) {
            $d = ['epcClass' => $qe->epcClass, 'quantity' => $qe->quantity];
            if ($qe->uom !== null) $d['uom'] = $qe->uom;
            return $d;
        }, $items);
    }

    private function sensorElementListJson(): array
    {
        return array_map(function ($se) {
            $m = $se->metadata;
            $meta = array_filter([
                'time'                 => $m->time,
                'deviceID'             => $m->deviceId,
                'deviceMetadata'       => $m->deviceMetadata,
                'rawData'              => $m->rawData,
                'startTime'            => $m->startTime,
                'endTime'              => $m->endTime,
                'bizRules'             => $m->bizRules,
                'dataProcessingMethod' => $m->dataProcessingMethod,
            ]);
            $reports = array_map(fn($r) => array_filter([
                'type'                 => $r->type,
                'value'                => $r->value,
                'uom'                  => $r->uom,
                'minValue'             => $r->minValue,
                'maxValue'             => $r->maxValue,
                'meanValue'            => $r->meanValue,
                'sDev'                 => $r->sDev,
            ], fn($v) => $v !== null), $se->reports);
            return array_filter(['sensorMetadata' => $meta, 'sensorReport' => $reports]);
        }, $this->sensorElementList);
    }

    private function persistentDispositionJson(): array
    {
        $pd = $this->persistentDisposition;
        $resolve = fn($v) => $v instanceof \BackedEnum ? $v->value : $v;
        $d = [];
        if (!empty($pd->unset)) $d['unset'] = array_map($resolve, $pd->unset);
        if (!empty($pd->set))   $d['set']   = array_map($resolve, $pd->set);
        return $d;
    }

    private function ilmdJson(): array
    {
        $d = [];
        foreach ($this->ilmd as $attr) {
            $d[$attr->localName] = $attr->value;
        }
        return $d;
    }
}
