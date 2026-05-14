<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use BackedEnum;
use DOMDocument;
use DOMElement;

abstract class EPCISBusinessEvent extends EPCISEvent
{
    /**
     * @param Source[]              $sourceList
     * @param Destination[]         $destinationList
     * @param BusinessTransaction[] $businessTransactionList
     * @param SensorElement[]       $sensorElementList
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
    ) {
        parent::__construct($eventTime, $eventTimezoneOffset, $recordTime, $eventId, $errorDeclaration, $sensorElementList);
    }

    protected function resolveEnum(BackedEnum|string|null $value): ?string
    {
        if ($value === null) return null;
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    protected function appendBusinessFields(DOMDocument $doc, DOMElement $el): void
    {
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
        if (!empty($this->businessTransactionList)) {
            $btl = $doc->createElement('bizTransactionList');
            foreach ($this->businessTransactionList as $bt) {
                $btEl = $doc->createElement('bizTransaction', $bt->bizTransaction);
                $btEl->setAttribute('type', $bt->type);
                $btl->appendChild($btEl);
            }
            $el->appendChild($btl);
        }
    }

    protected function appendSourceDestination(DOMDocument $doc, DOMElement $el): void
    {
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
    }

    protected function appendPersistentDisposition(DOMDocument $doc, DOMElement $el): void
    {
        if ($this->persistentDisposition === null || $this->persistentDisposition->isEmpty()) {
            return;
        }
        $pd = $doc->createElement('persistentDisposition');
        foreach ($this->persistentDisposition->unset as $v) {
            $pd->appendChild($doc->createElement('unset', $v instanceof BackedEnum ? $v->value : $v));
        }
        foreach ($this->persistentDisposition->set as $v) {
            $pd->appendChild($doc->createElement('set', $v instanceof BackedEnum ? $v->value : $v));
        }
        $el->appendChild($pd);
    }
}
