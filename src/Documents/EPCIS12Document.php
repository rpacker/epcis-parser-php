<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Documents;

use Rpacker\EpcisParser\Events\Action;
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\AssociationEvent;
use Rpacker\EpcisParser\Events\EPCISEvent;
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\QuantityElement;
use Rpacker\EpcisParser\Events\TransactionEvent;
use Rpacker\EpcisParser\Events\TransformationEvent;
use Rpacker\EpcisParser\MasterData\Vocabulary;
use Rpacker\EpcisParser\SBDH\StandardBusinessDocumentHeader;

/**
 * EPCIS 1.2 XML writer — the version most DSCSA trading partners still
 * exchange. Takes the same event objects as the 2.0 documents; what differs
 * is the XML shape:
 *
 * - namespace urn:epcglobal:epcis:xsd:1, schemaVersion="1.2";
 * - an <EPCISHeader> carrying the SBDH, master data under
 *   <extension><EPCISMasterData>, then any industry header elements;
 * - fields added after 1.0 live under each event's <extension>
 *   (sourceList, destinationList, ilmd, quantityList, …) and
 *   eventID/errorDeclaration under <baseExtension>;
 * - TransformationEvent is wrapped in <extension> inside the EventList.
 *
 * EPCIS 2.0-only content (AssociationEvent, sensorElementList,
 * persistentDisposition) has no 1.2 form and is rejected rather than
 * silently dropped.
 */
class EPCIS12Document
{
    public const NS_EPCIS  = 'urn:epcglobal:epcis:xsd:1';
    public const NS_SBDH   = 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader';
    public const NS_CBVMDA = 'urn:epcglobal:cbv:mda';

    private const NS_XMLNS = 'http://www.w3.org/2000/xmlns/';

    private string $creationDate;
    private \DOMDocument $doc;

    /**
     * @param EPCISEvent[]    $events         rendered in the given order
     * @param Vocabulary[]    $vocabularies   master data
     * @param HeaderElement[] $headerElements appended to the header after master data
     */
    public function __construct(
        public array $events = [],
        public ?StandardBusinessDocumentHeader $sbdh = null,
        public array $vocabularies = [],
        public array $headerElements = [],
        ?string $creationDate = null,
    ) {
        $this->creationDate = $creationDate
            ?? (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.v\Z');
    }

    public function render(): string
    {
        $this->doc               = new \DOMDocument('1.0', 'UTF-8');
        $this->doc->formatOutput = true;

        $root       = $this->doc->createElementNS(self::NS_EPCIS, 'epcis:EPCISDocument');
        $namespaces = ['cbvmda' => self::NS_CBVMDA];
        if ($this->sbdh !== null) {
            $namespaces['sbdh'] = self::NS_SBDH;
        }
        foreach ($this->headerElements as $element) {
            $namespaces += $element->namespaces();
        }
        foreach ($namespaces as $prefix => $uri) {
            $root->setAttributeNS(self::NS_XMLNS, "xmlns:{$prefix}", $uri);
        }
        $root->setAttribute('schemaVersion', '1.2');
        $root->setAttribute('creationDate', $this->creationDate);
        $this->doc->appendChild($root);

        $this->renderHeader($root);

        $eventList = $this->child($this->child($root, 'EPCISBody'), 'EventList');
        foreach ($this->events as $event) {
            $this->renderEvent($eventList, $event);
        }

        return $this->doc->saveXML();
    }

    private function renderHeader(\DOMElement $root): void
    {
        $vocabularies = array_filter($this->vocabularies, fn (Vocabulary $v) => $v->elements !== []);
        if ($this->sbdh === null && $vocabularies === [] && $this->headerElements === []) {
            return;
        }

        $header = $this->child($root, 'EPCISHeader');

        if ($this->sbdh !== null) {
            $this->renderSbdh($header, $this->sbdh);
        }

        if ($vocabularies !== []) {
            $list = $this->child($this->child($this->child($header, 'extension'), 'EPCISMasterData'), 'VocabularyList');
            foreach ($vocabularies as $vocabulary) {
                $vocabEl = $this->child($list, 'Vocabulary');
                $vocabEl->setAttribute('type', $vocabulary->typeUri());
                $elements = $this->child($vocabEl, 'VocabularyElementList');
                foreach ($vocabulary->elements as $element) {
                    $el = $this->child($elements, 'VocabularyElement');
                    $el->setAttribute('id', $element->id);
                    foreach ($element->attributes as $id => $value) {
                        if ($value === null || $value === '') {
                            continue;
                        }
                        $this->child($el, 'attribute', (string) $value)->setAttribute('id', $id);
                    }
                }
            }
        }

        foreach ($this->headerElements as $element) {
            $header->appendChild($element->render($this->doc));
        }
    }

    private function renderSbdh(\DOMElement $header, StandardBusinessDocumentHeader $sbdh): void
    {
        $el = $this->child($header, 'sbdh:StandardBusinessDocumentHeader', null, self::NS_SBDH);
        $this->child($el, 'sbdh:HeaderVersion', $sbdh->headerVersion, self::NS_SBDH);

        foreach (array_merge([['Sender', $sbdh->sender]], array_map(fn ($r) => ['Receiver', $r], $sbdh->receivers)) as [$role, $partner]) {
            $identifier = $this->child($this->child($el, "sbdh:{$role}", null, self::NS_SBDH), 'sbdh:Identifier', $partner->identifier, self::NS_SBDH);
            if ($partner->authority !== null) {
                $identifier->setAttribute('Authority', $partner->authority);
            }
        }

        $id    = $sbdh->documentIdentification;
        $docId = $this->child($el, 'sbdh:DocumentIdentification', null, self::NS_SBDH);
        $this->child($docId, 'sbdh:Standard', $id->standard, self::NS_SBDH);
        $this->child($docId, 'sbdh:TypeVersion', $id->typeVersion, self::NS_SBDH);
        $this->child($docId, 'sbdh:InstanceIdentifier', $id->instanceIdentifier !== '' ? $id->instanceIdentifier : self::uuidUrn(), self::NS_SBDH);
        $this->child($docId, 'sbdh:Type', $id->type, self::NS_SBDH);
        $this->child($docId, 'sbdh:CreationDateAndTime', $id->creationDateAndTime ?? $this->creationDate, self::NS_SBDH);
    }

    private function renderEvent(\DOMElement $eventList, EPCISEvent $event): void
    {
        if ($event instanceof AssociationEvent) {
            throw new \InvalidArgumentException('AssociationEvent is EPCIS 2.0-only and has no EPCIS 1.2 representation.');
        }
        if (! empty($event->sensorElementList)) {
            throw new \InvalidArgumentException('sensorElementList is EPCIS 2.0-only and has no EPCIS 1.2 representation.');
        }
        if (property_exists($event, 'persistentDisposition') && $event->persistentDisposition !== null && ! $event->persistentDisposition->isEmpty()) {
            throw new \InvalidArgumentException('persistentDisposition is EPCIS 2.0-only and has no EPCIS 1.2 representation.');
        }

        match (true) {
            $event instanceof ObjectEvent         => $this->renderObjectEvent($eventList, $event),
            $event instanceof AggregationEvent    => $this->renderAggregationEvent($eventList, $event),
            $event instanceof TransactionEvent    => $this->renderTransactionEvent($eventList, $event),
            $event instanceof TransformationEvent => $this->renderTransformationEvent($this->child($eventList, 'extension'), $event),
            default                               => throw new \InvalidArgumentException('Unsupported event type for EPCIS 1.2: ' . $event::class),
        };
    }

    // Element order below follows the EPCIS 1.2 XSD sequences exactly.

    private function renderObjectEvent(\DOMElement $parent, ObjectEvent $e): void
    {
        $el = $this->child($parent, 'ObjectEvent');
        $this->baseFields($el, $e);
        $this->epcList($el, 'epcList', $e->epcList, always: true);
        $this->businessFields($el, $e, withBizTransactions: true);
        $this->extension($el, function (\DOMElement $ext) use ($e) {
            $this->quantityList($ext, 'quantityList', $e->quantityList);
            $this->sourceDestination($ext, $e);
            $this->ilmd($ext, $e->ilmd);
        });
    }

    private function renderAggregationEvent(\DOMElement $parent, AggregationEvent $e): void
    {
        $el = $this->child($parent, 'AggregationEvent');
        $this->baseFields($el, $e);
        if ($e->parentId !== null) {
            $this->child($el, 'parentID', $e->parentId);
        }
        $this->epcList($el, 'childEPCs', $e->childEpcs, always: true);
        $this->businessFields($el, $e, withBizTransactions: true);
        $this->extension($el, function (\DOMElement $ext) use ($e) {
            $this->quantityList($ext, 'childQuantityList', $e->childQuantityList);
            $this->sourceDestination($ext, $e);
        });
    }

    private function renderTransactionEvent(\DOMElement $parent, TransactionEvent $e): void
    {
        $el = $this->child($parent, 'TransactionEvent');
        $this->baseFields($el, $e);
        // Required (at least one) in 1.2, and first.
        if ($e->businessTransactionList === []) {
            throw new \InvalidArgumentException('An EPCIS 1.2 TransactionEvent needs at least one business transaction.');
        }
        $this->bizTransactionList($el, $e->businessTransactionList);
        if ($e->parentId !== null) {
            $this->child($el, 'parentID', $e->parentId);
        }
        $this->epcList($el, 'epcList', $e->epcList, always: true);
        $this->businessFields($el, $e, withBizTransactions: false);
        $this->extension($el, function (\DOMElement $ext) use ($e) {
            $this->quantityList($ext, 'quantityList', $e->quantityList);
            $this->sourceDestination($ext, $e);
        });
    }

    private function renderTransformationEvent(\DOMElement $parent, TransformationEvent $e): void
    {
        // 1.1 addition, so its fields are inline (no nested <extension>).
        $el = $this->child($parent, 'TransformationEvent');
        $this->baseFields($el, $e);
        $this->epcList($el, 'inputEPCList', $e->inputEpcList);
        $this->quantityList($el, 'inputQuantityList', $e->inputQuantityList);
        $this->epcList($el, 'outputEPCList', $e->outputEpcList);
        $this->quantityList($el, 'outputQuantityList', $e->outputQuantityList);
        if ($e->transformationId !== null) {
            $this->child($el, 'transformationID', $e->transformationId);
        }
        $this->bizStepThroughLocation($el, $e->bizStep, $e->disposition, $e->readPoint, $e->bizLocation);
        $this->bizTransactionList($el, $e->businessTransactionList);
        $this->sourceDestination($el, $e);
        $this->ilmd($el, $e->ilmd);
    }

    private function baseFields(\DOMElement $el, EPCISEvent $e): void
    {
        $this->child($el, 'eventTime', $e->eventTime);
        if ($e->recordTime !== null) {
            $this->child($el, 'recordTime', $e->recordTime);
        }
        $this->child($el, 'eventTimeZoneOffset', $e->eventTimezoneOffset);

        if ($e->eventId === null && $e->errorDeclaration === null) {
            return;
        }
        $base = $this->child($el, 'baseExtension');
        if ($e->eventId !== null) {
            $this->child($base, 'eventID', $e->eventId);
        }
        if ($e->errorDeclaration !== null) {
            $ed = $this->child($base, 'errorDeclaration');
            $this->child($ed, 'declarationTime', $e->errorDeclaration->declarationTime);
            if ($e->errorDeclaration->reason !== null) {
                $this->child($ed, 'reason', $e->errorDeclaration->reason);
            }
            if ($e->errorDeclaration->correctiveEventIds !== []) {
                $ids = $this->child($ed, 'correctiveEventIDs');
                foreach ($e->errorDeclaration->correctiveEventIds as $id) {
                    $this->child($ids, 'correctiveEventID', $id);
                }
            }
        }
    }

    private function businessFields(\DOMElement $el, ObjectEvent|AggregationEvent|TransactionEvent $e, bool $withBizTransactions): void
    {
        $this->child($el, 'action', $e->action instanceof Action ? $e->action->value : $e->action);
        $this->bizStepThroughLocation($el, $e->bizStep, $e->disposition, $e->readPoint, $e->bizLocation);
        if ($withBizTransactions) {
            $this->bizTransactionList($el, $e->businessTransactionList);
        }
    }

    private function bizStepThroughLocation(\DOMElement $el, mixed $bizStep, mixed $disposition, ?string $readPoint, ?string $bizLocation): void
    {
        if ($bizStep !== null) {
            $this->child($el, 'bizStep', $bizStep instanceof \BackedEnum ? (string) $bizStep->value : $bizStep);
        }
        if ($disposition !== null) {
            $this->child($el, 'disposition', $disposition instanceof \BackedEnum ? (string) $disposition->value : $disposition);
        }
        if ($readPoint !== null) {
            $this->child($this->child($el, 'readPoint'), 'id', $readPoint);
        }
        if ($bizLocation !== null) {
            $this->child($this->child($el, 'bizLocation'), 'id', $bizLocation);
        }
    }

    private function bizTransactionList(\DOMElement $el, array $transactions): void
    {
        if ($transactions === []) {
            return;
        }
        $list = $this->child($el, 'bizTransactionList');
        foreach ($transactions as $bt) {
            $this->child($list, 'bizTransaction', $bt->bizTransaction)->setAttribute('type', $bt->type);
        }
    }

    /** @param string[] $epcs */
    private function epcList(\DOMElement $el, string $tag, array $epcs, bool $always = false): void
    {
        if ($epcs === [] && ! $always) {
            return;
        }
        $list = $this->child($el, $tag);
        foreach ($epcs as $epc) {
            $this->child($list, 'epc', $epc);
        }
    }

    /** @param QuantityElement[] $items */
    private function quantityList(\DOMElement $el, string $tag, array $items): void
    {
        if ($items === []) {
            return;
        }
        $list = $this->child($el, $tag);
        foreach ($items as $qe) {
            $q = $this->child($list, 'quantityElement');
            $this->child($q, 'epcClass', $qe->epcClass);
            $this->child($q, 'quantity', (string) $qe->quantity);
            if ($qe->uom !== null) {
                $this->child($q, 'uom', $qe->uom);
            }
        }
    }

    private function sourceDestination(\DOMElement $el, ObjectEvent|AggregationEvent|TransactionEvent|TransformationEvent $e): void
    {
        if ($e->sourceList !== []) {
            $list = $this->child($el, 'sourceList');
            foreach ($e->sourceList as $s) {
                $this->child($list, 'source', $s->source)->setAttribute('type', $s->type);
            }
        }
        if ($e->destinationList !== []) {
            $list = $this->child($el, 'destinationList');
            foreach ($e->destinationList as $d) {
                $this->child($list, 'destination', $d->destination)->setAttribute('type', $d->type);
            }
        }
    }

    private function ilmd(\DOMElement $el, array $attributes): void
    {
        if ($attributes === []) {
            return;
        }
        $ilmd = $this->child($el, 'ilmd');
        foreach ($attributes as $attr) {
            $attr->isCbv
                ? $this->child($ilmd, 'cbvmda:' . $attr->localName, $attr->value, self::NS_CBVMDA)
                : $this->child($ilmd, $attr->localName, $attr->value);
        }
    }

    /**
     * Adds <extension> only if $fill puts something in it.
     */
    private function extension(\DOMElement $el, callable $fill): void
    {
        $ext = $this->doc->createElement('extension');
        $fill($ext);
        if ($ext->hasChildNodes()) {
            $el->appendChild($ext);
        }
    }

    private function child(\DOMElement $parent, string $name, ?string $text = null, ?string $ns = null): \DOMElement
    {
        $el = $ns !== null ? $this->doc->createElementNS($ns, $name) : $this->doc->createElement($name);
        if ($text !== null) {
            // A text node, not createElement()'s value argument, which
            // doesn't escape "&".
            $el->appendChild($this->doc->createTextNode($text));
        }
        $parent->appendChild($el);

        return $el;
    }

    private static function uuidUrn(): string
    {
        $b    = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0F) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3F) | 0x80);

        return 'urn:uuid:' . vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
