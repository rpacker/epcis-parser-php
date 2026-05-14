<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

use PHPUnit\Framework\TestCase;
use Rpacker\EpcisParser\CBV\BusinessSteps;
use Rpacker\EpcisParser\CBV\Disposition;
use Rpacker\EpcisParser\CBV\InstanceLotMasterData;
use Rpacker\EpcisParser\CBV\SourceDestinationTypes;
use Rpacker\EpcisParser\Events\Action;
use Rpacker\EpcisParser\Events\BusinessTransaction;
use Rpacker\EpcisParser\Events\Destination;
use Rpacker\EpcisParser\Events\InstanceLotMasterDataAttribute;
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\PersistentDisposition;
use Rpacker\EpcisParser\Events\QuantityElement;
use Rpacker\EpcisParser\Events\SensorElement;
use Rpacker\EpcisParser\Events\SensorMetadata;
use Rpacker\EpcisParser\Events\SensorReport;
use Rpacker\EpcisParser\Events\Source;

class ObjectEventTest extends TestCase
{
    public function testBasicCommissioningEvent(): void
    {
        $event = new ObjectEvent(
            eventTime: '2025-10-14T14:11:35.000Z',
            eventTimezoneOffset: '+00:00',
            action: Action::Add,
            bizStep: BusinessSteps::Commissioning,
            disposition: Disposition::Active,
            readPoint: 'urn:epc:id:sgln:030003.000005.0',
            epcList: ['urn:epc:id:sgtin:030003.0029328.100011869390'],
            ilmd: [
                new InstanceLotMasterDataAttribute(InstanceLotMasterData::LotNumber, '8157771'),
                new InstanceLotMasterDataAttribute(InstanceLotMasterData::ItemExpirationDate, '2028-08-31'),
            ],
        );

        $xml = $this->wrapInDocument($event);

        $this->assertStringContainsString('urn:epcglobal:epcis:xsd:2', $xml);
        $this->assertStringContainsString('schemaVersion="2.0"', $xml);
        $this->assertStringContainsString('<ObjectEvent', $xml);
        $this->assertStringContainsString('urn:epc:id:sgtin:030003.0029328.100011869390', $xml);
        $this->assertStringContainsString('<action>ADD</action>', $xml);
        $this->assertStringContainsString('urn:epcglobal:cbv:bizstep:commissioning', $xml);
        $this->assertStringContainsString('urn:epcglobal:cbv:disp:active', $xml);
        $this->assertStringContainsString('<ilmd', $xml);
        $this->assertStringContainsString('8157771', $xml);
        // ilmd is top-level in 2.0 — NOT inside <extension>
        $this->assertStringNotContainsString('<extension>', $xml);
    }

    public function testQuantityListIsTopLevel(): void
    {
        $event = new ObjectEvent(
            eventTime: '2025-10-14T14:11:35.000Z',
            action: Action::Observe,
            quantityList: [
                new QuantityElement('urn:epc:class:lgtin:4012345.012345.998877', 200.5, 'KGM'),
            ],
        );

        $xml = $this->wrapInDocument($event);

        // In EPCIS 2.0, quantityList is a direct child of ObjectEvent, not inside <extension>
        $this->assertStringContainsString('<quantityList>', $xml);
        $this->assertStringNotContainsString('<extension>', $xml);
        $this->assertStringContainsString('<epcClass>urn:epc:class:lgtin:4012345.012345.998877</epcClass>', $xml);
        $this->assertStringContainsString('<quantity>200.5</quantity>', $xml);
        $this->assertStringContainsString('<uom>KGM</uom>', $xml);
    }

    public function testSourceDestinationIsTopLevel(): void
    {
        $event = new ObjectEvent(
            eventTime: '2025-10-14T14:11:35.000Z',
            action: Action::Observe,
            sourceList: [
                new Source(SourceDestinationTypes::OwningParty->value, 'urn:epc:id:pgln:4012345.00225'),
            ],
            destinationList: [
                new Destination(SourceDestinationTypes::OwningParty->value, 'urn:epc:id:pgln:0614141.00777'),
            ],
        );

        $xml = $this->wrapInDocument($event);

        // In EPCIS 2.0, sourceList/destinationList are direct children, NOT in <extension>
        $this->assertStringContainsString('<sourceList>', $xml);
        $this->assertStringContainsString('<destinationList>', $xml);
        $this->assertStringNotContainsString('<extension>', $xml);
    }

    public function testSensorElementList(): void
    {
        $event = new ObjectEvent(
            eventTime: '2025-10-14T14:11:35.000Z',
            action: Action::Observe,
            sensorElementList: [
                new SensorElement(
                    metadata: new SensorMetadata(
                        time: '2019-04-02T14:05:00.000+01:00',
                        deviceId: 'urn:epc:id:giai:4000001.111',
                    ),
                    reports: [
                        new SensorReport(type: 'gs1:Temperature', value: 26.0, uom: 'CEL'),
                    ],
                ),
            ],
        );

        $xml = $this->wrapInDocument($event);

        $this->assertStringContainsString('<sensorElementList>', $xml);
        $this->assertStringContainsString('<sensorElement>', $xml);
        $this->assertStringContainsString('deviceID="urn:epc:id:giai:4000001.111"', $xml);
        $this->assertStringContainsString('type="gs1:Temperature"', $xml);
        $this->assertStringContainsString('value="26"', $xml);
        $this->assertStringContainsString('uom="CEL"', $xml);
    }

    public function testPersistentDisposition(): void
    {
        $event = new ObjectEvent(
            eventTime: '2025-10-14T14:11:35.000Z',
            action: Action::Observe,
            persistentDisposition: new PersistentDisposition(
                set: ['urn:epcglobal:cbv:disp:completeness_verified'],
                unset: ['urn:epcglobal:cbv:disp:completeness_inferred'],
            ),
        );

        $xml = $this->wrapInDocument($event);

        $this->assertStringContainsString('<persistentDisposition>', $xml);
        $this->assertStringContainsString('<set>urn:epcglobal:cbv:disp:completeness_verified</set>', $xml);
        $this->assertStringContainsString('<unset>urn:epcglobal:cbv:disp:completeness_inferred</unset>', $xml);
    }

    public function testRenderJson(): void
    {
        $event = new ObjectEvent(
            eventTime: '2025-10-14T14:11:35.000Z',
            action: Action::Add,
            bizStep: BusinessSteps::Commissioning,
            epcList: ['urn:epc:id:sgtin:030003.0029328.100011869390'],
        );

        $json = $event->renderJson();

        $this->assertArrayHasKey('objectEvent', $json);
        $this->assertEquals('ADD', $json['objectEvent']['action']);
        $this->assertEquals('urn:epcglobal:cbv:bizstep:commissioning', $json['objectEvent']['bizStep']);
        $this->assertContains('urn:epc:id:sgtin:030003.0029328.100011869390', $json['objectEvent']['epcList']);
    }

    private function wrapInDocument(ObjectEvent $event): string
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
