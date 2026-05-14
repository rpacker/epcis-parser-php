<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Documents;

use DOMDocument;
use Rpacker\EpcisParser\Events\EPCISEvent;
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\AssociationEvent;
use Rpacker\EpcisParser\Events\TransactionEvent;
use Rpacker\EpcisParser\Events\TransformationEvent;

class EPCISDocument
{
    private string $creationDate;

    /**
     * @param ObjectEvent[]         $objectEvents
     * @param AggregationEvent[]    $aggregationEvents
     * @param AssociationEvent[]    $associationEvents
     * @param TransactionEvent[]    $transactionEvents
     * @param TransformationEvent[] $transformationEvents
     */
    public function __construct(
        public array $objectEvents = [],
        public array $aggregationEvents = [],
        public array $associationEvents = [],
        public array $transactionEvents = [],
        public array $transformationEvents = [],
        ?string $creationDate = null,
    ) {
        $this->creationDate = $creationDate
            ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    }

    public function render(): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS('urn:epcglobal:epcis:xsd:2', 'epcis:EPCISDocument');
        $root->setAttribute('schemaVersion', '2.0');
        $root->setAttribute('creationDate', $this->creationDate);

        // cbvmda namespace declared per-ilmd element to avoid PHP DOMDocument inline re-declaration issues

        $doc->appendChild($root);

        $body = $doc->createElement('EPCISBody');
        $root->appendChild($body);

        $eventList = $doc->createElement('EventList');
        $body->appendChild($eventList);

        foreach ($this->allEvents() as $event) {
            $event->renderFragment($doc, $eventList);
        }

        return $doc->saveXML();
    }

    public function renderJson(): array
    {
        $events = [];
        foreach ($this->allEvents() as $event) {
            $events[] = $event->renderJson();
        }
        return [
            'EPCISDocument' => [
                'schemaVersion' => '2.0',
                'creationDate'  => $this->creationDate,
                'EPCISBody'     => ['EventList' => $events],
            ],
        ];
    }

    public function renderPrettyJson(): string
    {
        return json_encode($this->renderJson(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private function allEvents(): array
    {
        return array_merge(
            $this->objectEvents,
            $this->aggregationEvents,
            $this->associationEvents,
            $this->transactionEvents,
            $this->transformationEvents,
        );
    }

    private function needsCbvmdaNs(): bool
    {
        foreach ($this->allEvents() as $event) {
            $ilmd = [];
            if ($event instanceof ObjectEvent)         $ilmd = $event->ilmd;
            if ($event instanceof TransformationEvent) $ilmd = $event->ilmd;
            foreach ($ilmd as $attr) {
                if ($attr->isCbv) return true;
            }
        }
        return false;
    }
}
