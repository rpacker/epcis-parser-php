<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

use PHPUnit\Framework\TestCase;
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\AssociationEvent;
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\TransformationEvent;
use Rpacker\EpcisParser\Parser\EPCISParser;
use Rpacker\EpcisParser\Parser\FlexibleNSParser;

class ParserTest extends TestCase
{
    private string $gs1ExamplesDir;

    protected function setUp(): void
    {
        $this->gs1ExamplesDir = __DIR__ . '/fixtures/GS1';
    }

    public function testParseAggregationEvent(): void
    {
        $xml = file_get_contents("{$this->gs1ExamplesDir}/aggregation_event.xml");
        $collected = [];

        $parser = new class($xml) extends EPCISParser {
            public array $events = [];
            protected function handleAggregationEvent(AggregationEvent $event): void {
                $this->events[] = $event;
            }
        };
        $parser->parse();

        $this->assertCount(1, $parser->events);
        $event = $parser->events[0];
        $this->assertEquals('2013-06-08T14:58:56.591Z', $event->eventTime);
        $this->assertEquals('+02:00', $event->eventTimezoneOffset);
        $this->assertEquals('urn:epc:id:sscc:0614141.1234567890', $event->parentId);
        $this->assertContains('urn:epc:id:sgtin:0614141.107346.2017', $event->childEpcs);
        $this->assertContains('urn:epc:id:sgtin:0614141.107346.2018', $event->childEpcs);
        $this->assertEquals('OBSERVE', $event->action->value);
        $this->assertEquals('urn:epcglobal:cbv:bizstep:receiving', $event->bizStep);
    }

    public function testParseAggregationEventAllFields(): void
    {
        $xml = file_get_contents("{$this->gs1ExamplesDir}/aggregation_event_all_possible_fields.xml");

        $parser = new class($xml) extends EPCISParser {
            public array $events = [];
            protected function handleAggregationEvent(AggregationEvent $event): void {
                $this->events[] = $event;
            }
        };
        $parser->parse();

        $this->assertCount(1, $parser->events);
        $event = $parser->events[0];

        // childQuantityList parsed at top level (2.0)
        $this->assertCount(2, $event->childQuantityList);
        $this->assertEquals('urn:epc:idpat:sgtin:4012345.098765.*', $event->childQuantityList[0]->epcClass);
        $this->assertEquals(10.0, $event->childQuantityList[0]->quantity);
        $this->assertEquals(200.5, $event->childQuantityList[1]->quantity);
        $this->assertEquals('KGM', $event->childQuantityList[1]->uom);

        // sourceList and destinationList at top level (2.0)
        $this->assertNotEmpty($event->sourceList);
        $this->assertNotEmpty($event->destinationList);

        // sensorElementList
        $this->assertCount(1, $event->sensorElementList);
        $se = $event->sensorElementList[0];
        $this->assertEquals('urn:epc:id:giai:4000001.111', $se->metadata->deviceId);
        $this->assertCount(1, $se->reports);
        $this->assertEquals('gs1:Temperature', $se->reports[0]->type);
        $this->assertEquals(26.0, $se->reports[0]->value);
    }

    public function testParseAssociationEvent(): void
    {
        $xml = file_get_contents("{$this->gs1ExamplesDir}/association_event.xml");

        $parser = new class($xml) extends EPCISParser {
            public array $events = [];
            protected function handleAssociationEvent(AssociationEvent $event): void {
                $this->events[] = $event;
            }
        };
        $parser->parse();

        $this->assertCount(1, $parser->events);
        $event = $parser->events[0];
        $this->assertEquals('urn:epc:id:grai:4012345.55555.987', $event->parentId);
        $this->assertContains('urn:epc:id:giai:4000001.12345', $event->childEpcs);
        $this->assertEquals('urn:epcglobal:cbv:bizstep:assembling', $event->bizStep);
    }

    public function testParseObjectEventWithSensorAndPersistentDisposition(): void
    {
        $xml = file_get_contents("{$this->gs1ExamplesDir}/object_event_all_possible_fields.xml");

        $parser = new class($xml) extends EPCISParser {
            public array $events = [];
            protected function handleObjectEvent(ObjectEvent $event): void {
                $this->events[] = $event;
            }
        };
        $parser->parse();

        $this->assertCount(1, $parser->events);
        $event = $parser->events[0];

        $this->assertNotEmpty($event->sensorElementList);
        $this->assertNotNull($event->persistentDisposition);
        $this->assertContains('urn:epcglobal:cbv:disp:completeness_verified', $event->persistentDisposition->set);
        $this->assertContains('urn:epcglobal:cbv:disp:completeness_inferred', $event->persistentDisposition->unset);
    }

    public function testParseTransformationEvent(): void
    {
        $xml = file_get_contents("{$this->gs1ExamplesDir}/transformation_event.xml");

        $parser = new class($xml) extends EPCISParser {
            public array $events = [];
            protected function handleTransformationEvent(TransformationEvent $event): void {
                $this->events[] = $event;
            }
        };
        $parser->parse();

        $this->assertCount(1, $parser->events);
        $event = $parser->events[0];
        $this->assertNotEmpty($event->outputEpcList);
        $this->assertNotEmpty($event->inputQuantityList);
        $this->assertEquals('urn:epcglobal:cbv:bizstep:commissioning', $event->bizStep);
        // ilmd parsed
        $this->assertNotEmpty($event->ilmd);
    }

    public function testRoundTrip(): void
    {
        // Generate an ObjectEvent, render to XML, parse it back
        $original = new ObjectEvent(
            eventTime: '2025-10-14T14:11:35.000Z',
            eventTimezoneOffset: '+00:00',
            action: \Rpacker\EpcisParser\Events\Action::Add,
            bizStep: \Rpacker\EpcisParser\CBV\BusinessSteps::Commissioning,
            disposition: \Rpacker\EpcisParser\CBV\Disposition::Active,
            readPoint: 'urn:epc:id:sgln:030003.000005.0',
            epcList: [
                'urn:epc:id:sgtin:030003.0029328.100011869390',
                'urn:epc:id:sgtin:030003.0029328.100011869391',
            ],
        );

        $doc = new \Rpacker\EpcisParser\Documents\EPCISDocument(
            objectEvents: [$original],
            creationDate: '2026-05-13T00:00:00.000Z',
        );
        $xml = $doc->render();

        $parser = new class($xml) extends EPCISParser {
            public array $events = [];
            protected function handleObjectEvent(ObjectEvent $event): void {
                $this->events[] = $event;
            }
        };
        $parser->parse();

        $this->assertCount(1, $parser->events);
        $parsed = $parser->events[0];

        $this->assertEquals('2025-10-14T14:11:35.000Z', $parsed->eventTime);
        $this->assertEquals('+00:00', $parsed->eventTimezoneOffset);
        $this->assertEquals('urn:epcglobal:cbv:bizstep:commissioning', $parsed->bizStep);
        $this->assertEquals('urn:epcglobal:cbv:disp:active', $parsed->disposition);
        $this->assertEquals('urn:epc:id:sgln:030003.000005.0', $parsed->readPoint);
        $this->assertContains('urn:epc:id:sgtin:030003.0029328.100011869390', $parsed->epcList);
        $this->assertContains('urn:epc:id:sgtin:030003.0029328.100011869391', $parsed->epcList);
    }

    public function testFlexibleNsParserAlias(): void
    {
        $xml = file_get_contents("{$this->gs1ExamplesDir}/aggregation_event.xml");

        $parser = new class($xml) extends FlexibleNSParser {
            public array $events = [];
            protected function handleAggregationEvent(AggregationEvent $event): void {
                $this->events[] = $event;
            }
        };
        $parser->parse();

        $this->assertCount(1, $parser->events);
    }
}
