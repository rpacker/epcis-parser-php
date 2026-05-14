<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

use PHPUnit\Framework\TestCase;
use Rpacker\EpcisParser\CBV\BusinessSteps;
use Rpacker\EpcisParser\CBV\InstanceLotMasterData;
use Rpacker\EpcisParser\Events\InstanceLotMasterDataAttribute;
use Rpacker\EpcisParser\Events\QuantityElement;
use Rpacker\EpcisParser\Events\TransformationEvent;

class TransformationEventTest extends TestCase
{
    public function testBasicTransformationEvent(): void
    {
        $event = new TransformationEvent(
            eventTime: '2020-09-29T14:00:00.000+02:00',
            eventTimezoneOffset: '+02:00',
            bizStep: BusinessSteps::Commissioning,
            readPoint: 'urn:epc:id:sgln:4023333.00000.0',
            inputQuantityList: [
                new QuantityElement('urn:epc:class:lgtin:4023333.055555.ABC123', 25.0, 'KGM'),
            ],
            outputEpcList: [
                'urn:epc:id:sgtin:4012345.012345.987',
                'urn:epc:id:sgtin:4012345.012345.988',
            ],
            ilmd: [
                new InstanceLotMasterDataAttribute(InstanceLotMasterData::LotNumber, 'LOTABC'),
            ],
        );

        $xml = $this->renderXml($event);

        $this->assertStringContainsString('<TransformationEvent', $xml);
        $this->assertStringContainsString('<inputQuantityList>', $xml);
        $this->assertStringContainsString('<outputEPCList>', $xml);
        $this->assertStringContainsString('urn:epc:id:sgtin:4012345.012345.987', $xml);
        $this->assertStringContainsString('urn:epcglobal:cbv:bizstep:commissioning', $xml);
        // In EPCIS 2.0, ilmd is a direct child (no <extension> wrapper)
        $this->assertStringContainsString('<ilmd', $xml);
        $this->assertStringContainsString('LOTABC', $xml);
    }

    public function testTransformationEventIsDirectEventListChild(): void
    {
        // In EPCIS 2.0, TransformationEvent is directly inside EventList (not in <extension>)
        $event = new TransformationEvent(eventTime: '2020-09-29T14:00:00.000+02:00');
        $xml = $this->renderXml($event);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);

        // TransformationEvent should be a direct child of EventList
        $nodes = $xpath->query('//*[local-name()="EventList"]/*[local-name()="TransformationEvent"]');
        $this->assertEquals(1, $nodes->length);

        // There should be NO <extension> wrapping TransformationEvent
        $extNodes = $xpath->query('//*[local-name()="EventList"]/*[local-name()="extension"]');
        $this->assertEquals(0, $extNodes->length);
    }

    private function renderXml(TransformationEvent $event): string
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
