<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

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
        public \BackedEnum|string|null $bizStep = null,
        public \BackedEnum|string|null $disposition = null,
        public ?string $readPoint = null,
        public ?string $bizLocation = null,
        public array $sourceList = [],
        public array $destinationList = [],
        public array $businessTransactionList = [],
        public ?PersistentDisposition $persistentDisposition = null,
    ) {
        parent::__construct($eventTime, $eventTimezoneOffset, $recordTime, $eventId, $errorDeclaration, $sensorElementList);
    }

    protected function resolveEnum(\BackedEnum|string|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    protected function appendBusinessFields(\DOMDocument $doc, \DOMElement $el): void
    {
        $action = $this->action instanceof Action ? $this->action->value : $this->action;
        $el->appendChild($this->textElement($doc, 'action', $action));

        if ($this->bizStep !== null) {
            $el->appendChild($this->textElement($doc, 'bizStep', $this->resolveEnum($this->bizStep)));
        }
        if ($this->disposition !== null) {
            $el->appendChild($this->textElement($doc, 'disposition', $this->resolveEnum($this->disposition)));
        }
        if ($this->readPoint !== null) {
            $rp = $doc->createElement('readPoint');
            $rp->appendChild($this->textElement($doc, 'id', $this->readPoint));
            $el->appendChild($rp);
        }
        if ($this->bizLocation !== null) {
            $bl = $doc->createElement('bizLocation');
            $bl->appendChild($this->textElement($doc, 'id', $this->bizLocation));
            $el->appendChild($bl);
        }
        if (! empty($this->businessTransactionList)) {
            $btl = $doc->createElement('bizTransactionList');
            foreach ($this->businessTransactionList as $bt) {
                $btEl = $this->textElement($doc, 'bizTransaction', $bt->bizTransaction);
                $btEl->setAttribute('type', $bt->type);
                $btl->appendChild($btEl);
            }
            $el->appendChild($btl);
        }
    }

    protected function appendSourceDestination(\DOMDocument $doc, \DOMElement $el): void
    {
        if (! empty($this->sourceList)) {
            $sl = $doc->createElement('sourceList');
            foreach ($this->sourceList as $s) {
                $sEl = $this->textElement($doc, 'source', $s->source);
                $sEl->setAttribute('type', $s->type);
                $sl->appendChild($sEl);
            }
            $el->appendChild($sl);
        }
        if (! empty($this->destinationList)) {
            $dl = $doc->createElement('destinationList');
            foreach ($this->destinationList as $d) {
                $dEl = $this->textElement($doc, 'destination', $d->destination);
                $dEl->setAttribute('type', $d->type);
                $dl->appendChild($dEl);
            }
            $el->appendChild($dl);
        }
    }

    protected function appendPersistentDisposition(\DOMDocument $doc, \DOMElement $el): void
    {
        if ($this->persistentDisposition === null || $this->persistentDisposition->isEmpty()) {
            return;
        }
        $pd = $doc->createElement('persistentDisposition');
        foreach ($this->persistentDisposition->unset as $v) {
            $pd->appendChild($this->textElement($doc, 'unset', $v instanceof \BackedEnum ? $v->value : $v));
        }
        foreach ($this->persistentDisposition->set as $v) {
            $pd->appendChild($this->textElement($doc, 'set', $v instanceof \BackedEnum ? $v->value : $v));
        }
        $el->appendChild($pd);
    }
}
