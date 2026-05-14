<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Documents;

use DOMDocument;
use Rpacker\EpcisParser\Events\EPCISEvent;

class EPCISEventListDocument
{
    private string $creationDate;

    /** @param EPCISEvent[] $events */
    public function __construct(
        public array $events = [],
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
        $doc->appendChild($root);

        $body = $doc->createElement('EPCISBody');
        $root->appendChild($body);

        $eventList = $doc->createElement('EventList');
        $body->appendChild($eventList);

        foreach ($this->events as $event) {
            $event->renderFragment($doc, $eventList);
        }

        return $doc->saveXML();
    }

    public function renderJson(): array
    {
        return [
            'EPCISDocument' => [
                'schemaVersion' => '2.0',
                'creationDate'  => $this->creationDate,
                'EPCISBody'     => [
                    'EventList' => array_map(fn($e) => $e->renderJson(), $this->events),
                ],
            ],
        ];
    }
}
