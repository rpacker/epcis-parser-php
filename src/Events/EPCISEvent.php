<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use DOMDocument;
use DOMElement;

abstract class EPCISEvent
{
    /**
     * @param SensorElement[] $sensorElementList
     */
    public function __construct(
        public string $eventTime = '',
        public string $eventTimezoneOffset = '+00:00',
        public ?string $recordTime = null,
        public ?string $eventId = null,
        public ?ErrorDeclaration $errorDeclaration = null,
        public array $sensorElementList = [],
    ) {
        if ($this->eventTime === '') {
            $this->eventTime = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format('Y-m-d\TH:i:s.v\Z');
        }
    }

    abstract public function renderFragment(DOMDocument $doc, DOMElement $parent): DOMElement;

    protected function appendBaseFields(DOMDocument $doc, DOMElement $el): void
    {
        $el->appendChild($doc->createElement('eventTime', $this->eventTime));
        if ($this->recordTime !== null) {
            $el->appendChild($doc->createElement('recordTime', $this->recordTime));
        }
        $el->appendChild($doc->createElement('eventTimeZoneOffset', $this->eventTimezoneOffset));
        if ($this->eventId !== null) {
            $el->appendChild($doc->createElement('eventID', $this->eventId));
        }
        if ($this->errorDeclaration !== null) {
            $el->appendChild($this->buildErrorDeclaration($doc));
        }
    }

    protected function appendSensorElementList(DOMDocument $doc, DOMElement $parent): void
    {
        if (empty($this->sensorElementList)) {
            return;
        }
        $list = $doc->createElement('sensorElementList');
        foreach ($this->sensorElementList as $se) {
            $seEl = $doc->createElement('sensorElement');
            $meta = $doc->createElement('sensorMetadata');
            $m = $se->metadata;
            if ($m->time !== null)                 $meta->setAttribute('time', $m->time);
            if ($m->deviceId !== null)             $meta->setAttribute('deviceID', $m->deviceId);
            if ($m->deviceMetadata !== null)       $meta->setAttribute('deviceMetadata', $m->deviceMetadata);
            if ($m->rawData !== null)              $meta->setAttribute('rawData', $m->rawData);
            if ($m->startTime !== null)            $meta->setAttribute('startTime', $m->startTime);
            if ($m->endTime !== null)              $meta->setAttribute('endTime', $m->endTime);
            if ($m->bizRules !== null)             $meta->setAttribute('bizRules', $m->bizRules);
            if ($m->dataProcessingMethod !== null) $meta->setAttribute('dataProcessingMethod', $m->dataProcessingMethod);
            $seEl->appendChild($meta);
            foreach ($se->reports as $r) {
                $rEl = $doc->createElement('sensorReport');
                if ($r->type !== null)                $rEl->setAttribute('type', $r->type);
                if ($r->value !== null)               $rEl->setAttribute('value', (string) $r->value);
                if ($r->uom !== null)                 $rEl->setAttribute('uom', $r->uom);
                if ($r->minValue !== null)            $rEl->setAttribute('minValue', (string) $r->minValue);
                if ($r->maxValue !== null)            $rEl->setAttribute('maxValue', (string) $r->maxValue);
                if ($r->meanValue !== null)           $rEl->setAttribute('meanValue', (string) $r->meanValue);
                if ($r->sDev !== null)                $rEl->setAttribute('sDev', (string) $r->sDev);
                if ($r->percRank !== null)            $rEl->setAttribute('percRank', (string) $r->percRank);
                if ($r->percValue !== null)           $rEl->setAttribute('percValue', (string) $r->percValue);
                if ($r->chemicalSubstance !== null)   $rEl->setAttribute('chemicalSubstance', $r->chemicalSubstance);
                if ($r->microorganism !== null)       $rEl->setAttribute('microorganism', $r->microorganism);
                if ($r->deviceId !== null)            $rEl->setAttribute('deviceID', $r->deviceId);
                if ($r->deviceMetadata !== null)      $rEl->setAttribute('deviceMetadata', $r->deviceMetadata);
                if ($r->rawData !== null)             $rEl->setAttribute('rawData', $r->rawData);
                if ($r->time !== null)                $rEl->setAttribute('time', $r->time);
                if ($r->component !== null)           $rEl->setAttribute('component', $r->component);
                if ($r->stringValue !== null)         $rEl->setAttribute('stringValue', $r->stringValue);
                if ($r->booleanValue !== null)        $rEl->setAttribute('booleanValue', $r->booleanValue ? 'true' : 'false');
                if ($r->hexBinaryValue !== null)      $rEl->setAttribute('hexBinaryValue', $r->hexBinaryValue);
                if ($r->uriValue !== null)            $rEl->setAttribute('uriValue', $r->uriValue);
                if ($r->dataProcessingMethod !== null) $rEl->setAttribute('dataProcessingMethod', $r->dataProcessingMethod);
                $seEl->appendChild($rEl);
            }
            $list->appendChild($seEl);
        }
        $parent->appendChild($list);
    }

    private function buildErrorDeclaration(DOMDocument $doc): DOMElement
    {
        $ed = $this->errorDeclaration;
        $el = $doc->createElement('errorDeclaration');
        $el->appendChild($doc->createElement('declarationTime', $ed->declarationTime));
        if ($ed->reason !== null) {
            $el->appendChild($doc->createElement('reason', $ed->reason));
        }
        foreach ($ed->correctiveEventIds as $id) {
            $el->appendChild($doc->createElement('correctiveEventID', $id));
        }
        return $el;
    }
}
