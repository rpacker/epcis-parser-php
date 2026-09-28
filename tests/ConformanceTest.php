<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

use PHPUnit\Framework\TestCase;
use Rpacker\EpcisParser\CBV\InstanceLotMasterData;
use Rpacker\EpcisParser\Documents\EPCIS12Document;
use Rpacker\EpcisParser\Documents\EPCISEventListDocument;
use Rpacker\EpcisParser\Events\Action;
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\AssociationEvent;
use Rpacker\EpcisParser\Events\BusinessTransaction;
use Rpacker\EpcisParser\Events\EPCISEvent;
use Rpacker\EpcisParser\Events\ErrorDeclaration;
use Rpacker\EpcisParser\Events\InstanceLotMasterDataAttribute;
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\QuantityElement;
use Rpacker\EpcisParser\Events\TransactionEvent;
use Rpacker\EpcisParser\Events\TransformationEvent;
use Rpacker\EpcisParser\Parser\EPCISParser;

/**
 * Writer/parser behaviour checked against GS1's own EPCIS 2.0 example
 * documents (tests/fixtures/GS1), plus input-handling guarantees.
 */
class ConformanceTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/fixtures/GS1';

    /** Event children whose relative order the XSD fixes. */
    private const ORDERED = [
        'eventTime', 'recordTime', 'eventTimeZoneOffset', 'eventID', 'errorDeclaration', 'parentID',
        'epcList', 'childEPCs', 'inputEPCList', 'inputQuantityList', 'outputEPCList', 'outputQuantityList',
        'transformationID', 'action', 'bizStep', 'disposition', 'persistentDisposition', 'readPoint',
        'bizLocation', 'bizTransactionList', 'quantityList', 'childQuantityList', 'sourceList',
        'destinationList', 'sensorElementList', 'ilmd',
    ];

    private const EVENT_TYPES = ['ObjectEvent', 'AggregationEvent', 'AssociationEvent', 'TransactionEvent', 'TransformationEvent'];

    /** @return EPCISEvent[] */
    private function parse(string $xml): array
    {
        $parser = new class($xml) extends EPCISParser {
            public array $events = [];

            protected function handleObjectEvent(ObjectEvent $e): void
            {
                $this->events[] = $e;
            }

            protected function handleAggregationEvent(AggregationEvent $e): void
            {
                $this->events[] = $e;
            }

            protected function handleAssociationEvent(AssociationEvent $e): void
            {
                $this->events[] = $e;
            }

            protected function handleTransactionEvent(TransactionEvent $e): void
            {
                $this->events[] = $e;
            }

            protected function handleTransformationEvent(TransformationEvent $e): void
            {
                $this->events[] = $e;
            }
        };
        $parser->parse();

        return $parser->events;
    }

    /** @return string[] */
    private function orderedChildren(\DOMElement $el): array
    {
        $names = [];
        foreach ($el->childNodes as $child) {
            if ($child instanceof \DOMElement && in_array($child->localName, self::ORDERED, true)) {
                $names[] = $child->localName;
            }
        }

        return $names;
    }

    /** @return array<string, array{string}> */
    public static function gs1Examples(): array
    {
        $files = [];
        foreach (glob(self::FIXTURES . '/*.xml') as $path) {
            $files[basename($path)] = [$path];
        }

        return $files;
    }

    /**
     * Parse each GS1 example and render it back: every element that
     * survives must keep the order GS1 wrote it in.
     *
     * @dataProvider gs1Examples
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('gs1Examples')]
    public function testRenderingAGs1ExampleKeepsItsElementOrder(string $path): void
    {
        $events = $this->parse(file_get_contents($path));
        if ($events === []) {
            $this->assertTrue(true, 'no events (master data / query examples)');

            return;
        }

        $original = new \DOMXPath($this->load(file_get_contents($path)));
        $rendered = new \DOMXPath($this->load((new EPCISEventListDocument($events))->render()));

        foreach (self::EVENT_TYPES as $type) {
            $originals = $original->query("//*[local-name()='{$type}']");
            $outputs   = $rendered->query("//*[local-name()='{$type}']");
            $this->assertSame($originals->length, $outputs->length, "{$type} count");

            for ($i = 0; $i < $originals->length; $i++) {
                $want = $this->orderedChildren($originals->item($i));
                $got  = array_values(array_intersect($this->orderedChildren($outputs->item($i)), $want));
                $this->assertSame(array_values(array_intersect($want, $got)), $got, "{$type} #{$i} element order");
            }
        }
    }

    private function load(string $xml): \DOMDocument
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml, LIBXML_NONET));

        return $dom;
    }

    // --- writer -------------------------------------------------------------

    public function testTextIsEscapedNotDropped(): void
    {
        $event = new ObjectEvent(
            action: Action::Add,
            epcList: ['urn:epc:id:sgtin:0360505.061326.1'],
            businessTransactionList: [new BusinessTransaction('urn:epcglobal:cbv:btt:po', 'urn:epcglobal:cbv:bt:0614141000005:A&B<1>')],
            ilmd: [new InstanceLotMasterDataAttribute(InstanceLotMasterData::LotNumber, 'LOT&1')],
        );

        $events = $this->parse((new EPCISEventListDocument([$event]))->render());

        $this->assertSame('urn:epcglobal:cbv:bt:0614141000005:A&B<1>', $events[0]->businessTransactionList[0]->bizTransaction);
        $this->assertSame('LOT&1', $events[0]->ilmd[0]->value);
    }

    public function testCorrectiveEventIdsAreWrapped(): void
    {
        $event = new ObjectEvent(
            eventId: 'urn:uuid:374d95fc-9457-4a51-bd6a-0bba133845a8',
            errorDeclaration: new ErrorDeclaration('2020-01-15T00:00:00+01:00', 'urn:epcglobal:cbv:er:incorrect_data', [
                'urn:uuid:404d95fc-9457-4a51-bd6a-0bba133845a8',
                'urn:uuid:504d95fc-9457-4a51-bd6a-0bba133845a8',
            ]),
            action: Action::Observe,
            epcList: ['urn:epc:id:sgtin:0360505.061326.1'],
        );
        $x = new \DOMXPath($this->load((new EPCISEventListDocument([$event]))->render()));

        $this->assertSame(2, $x->query('//errorDeclaration/correctiveEventIDs/correctiveEventID')->length);
        $this->assertSame(0, $x->query('//errorDeclaration/correctiveEventID')->length);
    }

    public function testAssociationEventPutsChildQuantityListBeforeAction(): void
    {
        $event = new AssociationEvent(
            action: Action::Add,
            parentId: 'urn:epc:id:grai:4012345.55555.987',
            childEpcs: ['urn:epc:id:giai:4000001.12345'],
            childQuantityList: [new QuantityElement('urn:epc:class:lgtin:4012345.012345.998877', 200, 'KGM')],
        );
        $el = $this->load((new EPCISEventListDocument([$event]))->render())->getElementsByTagName('AssociationEvent')->item(0);

        $this->assertSame(['eventTime', 'eventTimeZoneOffset', 'parentID', 'childEPCs', 'childQuantityList', 'action'], $this->orderedChildren($el));
    }

    public function testAggregationEventKeepsChildQuantityListAfterBusinessFields(): void
    {
        $event = new AggregationEvent(
            action: Action::Add,
            parentId: 'urn:epc:id:sscc:0614141.1234567890',
            childEpcs: ['urn:epc:id:sgtin:0614141.107346.2017'],
            childQuantityList: [new QuantityElement('urn:epc:class:lgtin:4012345.012345.998877', 200, 'KGM')],
        );
        $el = $this->load((new EPCISEventListDocument([$event]))->render())->getElementsByTagName('AggregationEvent')->item(0);

        $this->assertSame(['eventTime', 'eventTimeZoneOffset', 'parentID', 'childEPCs', 'action', 'childQuantityList'], $this->orderedChildren($el));
    }

    // --- parser -------------------------------------------------------------

    public function testParsesCorrectiveEventIdsFromTheGs1Example(): void
    {
        $events   = $this->parse(file_get_contents(self::FIXTURES . '/error_declaration_and_corrective_event.xml'));
        $declared = array_values(array_filter($events, fn ($e) => $e->errorDeclaration !== null));

        $this->assertSame(['urn:uuid:404d95fc-9457-4a51-bd6a-0bba133845a8'], $declared[0]->errorDeclaration->correctiveEventIds);
    }

    public function testParsesEpcis12BaseExtension(): void
    {
        $xml = (new EPCIS12Document([new ObjectEvent(
            eventId: 'urn:uuid:374d95fc-9457-4a51-bd6a-0bba133845a8',
            errorDeclaration: new ErrorDeclaration('2020-01-15T00:00:00+01:00', null, ['urn:uuid:404d95fc-9457-4a51-bd6a-0bba133845a8']),
            action: Action::Observe,
            epcList: ['urn:epc:id:sgtin:0360505.061326.1'],
        )]))->render();

        $event = $this->parse($xml)[0];

        $this->assertSame('urn:uuid:374d95fc-9457-4a51-bd6a-0bba133845a8', $event->eventId);
        $this->assertSame(['urn:uuid:404d95fc-9457-4a51-bd6a-0bba133845a8'], $event->errorDeclaration->correctiveEventIds);
    }

    public function testDocumentWithAByteOrderMarkParses(): void
    {
        $xml = file_get_contents(self::FIXTURES . '/object_event.xml');

        $this->assertCount(count($this->parse($xml)), $this->parse("\xEF\xBB\xBF" . $xml));
        $this->assertNotEmpty($this->parse("\xEF\xBB\xBF" . $xml));
    }

    public function testAPathIsNotTreatedAsADocument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not well-formed XML');

        $this->parse(self::FIXTURES . '/object_event.xml');
    }

    public function testMalformedXmlThrowsInsteadOfYieldingNoEvents(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parse('<epcis:EPCISDocument xmlns:epcis="urn:epcglobal:epcis:xsd:2"><EPCISBody>');
    }

    public function testExternalEntitiesAreNotFetched(): void
    {
        $xml = '<?xml version="1.0"?><!DOCTYPE d [<!ENTITY x SYSTEM "' . self::FIXTURES . '/object_event.xml">]>'
            . '<epcis:EPCISDocument xmlns:epcis="urn:epcglobal:epcis:xsd:2"><EPCISBody><EventList>&x;</EventList></EPCISBody></epcis:EPCISDocument>';

        $this->assertSame([], $this->parse($xml));
    }

    public function testFromFileReadsADocumentFromDisk(): void
    {
        $parser = EPCISParser::fromFile(self::FIXTURES . '/object_event.xml');
        $this->assertInstanceOf(EPCISParser::class, $parser);

        $this->expectException(\InvalidArgumentException::class);
        EPCISParser::fromFile(self::FIXTURES . '/missing.xml');
    }
}
