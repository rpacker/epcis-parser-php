<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

use PHPUnit\Framework\TestCase;
use Rpacker\EpcisParser\CBV\BusinessSteps;
use Rpacker\EpcisParser\CBV\Disposition;
use Rpacker\EpcisParser\Events\Action;
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\AssociationEvent;
use Rpacker\EpcisParser\Events\PersistentDisposition;
use Rpacker\EpcisParser\Events\QuantityElement;

class AggregationEventTest extends TestCase
{
    public function testBasicAggregationEvent(): void
    {
        $event = new AggregationEvent(
            eventTime: '2013-06-08T14:58:56.591Z',
            eventTimezoneOffset: '+02:00',
            action: Action::Observe,
            bizStep: BusinessSteps::Receiving,
            disposition: Disposition::InProgress,
            readPoint: 'urn:epc:id:sgln:0614141.00777.0',
            bizLocation: 'urn:epc:id:sgln:0614141.00888.0',
            parentId: 'urn:epc:id:sscc:0614141.1234567890',
            childEpcs: [
                'urn:epc:id:sgtin:0614141.107346.2017',
                'urn:epc:id:sgtin:0614141.107346.2018',
            ],
        );

        $xml = $this->renderXml($event);

        $this->assertStringContainsString('<AggregationEvent>', $xml);
        $this->assertStringContainsString('<parentID>urn:epc:id:sscc:0614141.1234567890</parentID>', $xml);
        $this->assertStringContainsString('<action>OBSERVE</action>', $xml);
        $this->assertStringContainsString('urn:epcglobal:cbv:bizstep:receiving', $xml);
        $this->assertStringContainsString('urn:epc:id:sgtin:0614141.107346.2017', $xml);
    }

    public function testChildQuantityListIsTopLevel(): void
    {
        $event = new AggregationEvent(
            eventTime: '2013-06-08T14:58:56.591Z',
            action: Action::Observe,
            parentId: 'urn:epc:id:sscc:0614141.1234567890',
            childQuantityList: [
                new QuantityElement('urn:epc:idpat:sgtin:4012345.098765.*', 10.0),
                new QuantityElement('urn:epc:class:lgtin:4012345.012345.998877', 200.5, 'KGM'),
            ],
        );

        $xml = $this->renderXml($event);

        $this->assertStringContainsString('<childQuantityList>', $xml);
        $this->assertStringNotContainsString('<extension>', $xml);
        $this->assertStringContainsString('<quantity>10</quantity>', $xml);
        $this->assertStringContainsString('<uom>KGM</uom>', $xml);
    }

    public function testAssociationEvent(): void
    {
        $event = new AssociationEvent(
            eventTime: '2019-11-01T14:00:00.000+01:00',
            eventTimezoneOffset: '+01:00',
            action: Action::Add,
            bizStep: BusinessSteps::Assembling,
            readPoint: 'urn:epc:id:sgln:4012345.00001.0',
            parentId: 'urn:epc:id:grai:4012345.55555.987',
            childEpcs: ['urn:epc:id:giai:4000001.12345'],
        );

        $xml = $this->renderXml($event);

        $this->assertStringContainsString('<AssociationEvent>', $xml);
        $this->assertStringContainsString('<parentID>urn:epc:id:grai:4012345.55555.987</parentID>', $xml);
        $this->assertStringContainsString('<action>ADD</action>', $xml);
        $this->assertStringContainsString('urn:epcglobal:cbv:bizstep:assembling', $xml);
        // Not an AggregationEvent
        $this->assertStringNotContainsString('<AggregationEvent>', $xml);
    }

    public function testPersistentDispositionOnAggregation(): void
    {
        $event = new AggregationEvent(
            eventTime: '2020-06-07T17:10:16Z',
            action: Action::Observe,
            bizStep: BusinessSteps::Receiving,
            persistentDisposition: new PersistentDisposition(
                set: ['urn:epcglobal:cbv:disp:completeness_inferred'],
            ),
        );

        $xml = $this->renderXml($event);

        $this->assertStringContainsString('<persistentDisposition>', $xml);
        $this->assertStringContainsString('<set>urn:epcglobal:cbv:disp:completeness_inferred</set>', $xml);
    }

    public function testJsonRendering(): void
    {
        $event = new AggregationEvent(
            eventTime: '2013-06-08T14:58:56.591Z',
            action: Action::Observe,
            parentId: 'urn:epc:id:sscc:0614141.1234567890',
            childEpcs: ['urn:epc:id:sgtin:0614141.107346.2017'],
        );

        $json = $event->renderJson();

        $this->assertArrayHasKey('aggregationEvent', $json);
        $this->assertEquals('urn:epc:id:sscc:0614141.1234567890', $json['aggregationEvent']['parentID']);
        $this->assertContains('urn:epc:id:sgtin:0614141.107346.2017', $json['aggregationEvent']['childEPCs']);
    }

    public function testAssociationEventJsonKey(): void
    {
        $event = new AssociationEvent(
            eventTime: '2019-11-01T14:00:00.000+01:00',
            action: Action::Add,
            parentId: 'urn:epc:id:grai:4012345.55555.987',
            childEpcs: ['urn:epc:id:giai:4000001.12345'],
        );

        $json = $event->renderJson();
        $this->assertArrayHasKey('associationEvent', $json);
    }

    private function renderXml(AggregationEvent $event): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElementNS('urn:epcglobal:epcis:xsd:2', 'epcis:EPCISDocument');
        $root->setAttribute('schemaVersion', '2.0');
        $doc->appendChild($root);
        $body = $doc->createElement('EPCISBody');
        $root->appendChild($body);
        $eventList = $doc->createElement('EventList');
        $body->appendChild($eventList);
        $event->renderFragment($doc, $eventList);
        return $doc->saveXML();
    }
}
