<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

use PHPUnit\Framework\TestCase;
use Rpacker\EpcisParser\CBV\BusinessSteps;
use Rpacker\EpcisParser\CBV\Disposition;
use Rpacker\EpcisParser\CBV\InstanceLotMasterData;
use Rpacker\EpcisParser\Documents\EPCISDocument;
use Rpacker\EpcisParser\Events\Action;
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\AssociationEvent;
use Rpacker\EpcisParser\Events\InstanceLotMasterDataAttribute;
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\TransactionEvent;
use Rpacker\EpcisParser\Events\TransformationEvent;

class EPCISDocumentTest extends TestCase
{
    public function testDocumentHasCorrect20Schema(): void
    {
        $doc = new EPCISDocument(creationDate: '2026-05-13T00:00:00.000Z');
        $xml = $doc->render();

        $this->assertStringContainsString('urn:epcglobal:epcis:xsd:2', $xml);
        $this->assertStringContainsString('schemaVersion="2.0"', $xml);
        $this->assertStringNotContainsString('xsd:1', $xml);
        $this->assertStringNotContainsString('schemaVersion="1.2"', $xml);
    }

    public function testAllFiveEventTypes(): void
    {
        $doc = new EPCISDocument(
            objectEvents: [
                new ObjectEvent(
                    eventTime: '2025-10-14T14:11:35.000Z',
                    action: Action::Add,
                    bizStep: BusinessSteps::Commissioning,
                    epcList: ['urn:epc:id:sgtin:030003.0029328.100011869390'],
                ),
            ],
            aggregationEvents: [
                new AggregationEvent(
                    eventTime: '2013-06-08T14:58:56.591Z',
                    action: Action::Observe,
                    parentId: 'urn:epc:id:sscc:0614141.1234567890',
                    childEpcs: ['urn:epc:id:sgtin:0614141.107346.2017'],
                ),
            ],
            associationEvents: [
                new AssociationEvent(
                    eventTime: '2019-11-01T14:00:00.000+01:00',
                    action: Action::Add,
                    parentId: 'urn:epc:id:grai:4012345.55555.987',
                    childEpcs: ['urn:epc:id:giai:4000001.12345'],
                ),
            ],
            transactionEvents: [
                new TransactionEvent(
                    eventTime: '2020-07-03T00:05:00Z',
                    action: Action::Add,
                    epcList: ['urn:epc:id:sgtin:0614141.107340.1'],
                ),
            ],
            transformationEvents: [
                new TransformationEvent(
                    eventTime: '2020-09-29T14:00:00.000+02:00',
                    outputEpcList: ['urn:epc:id:sgtin:4012345.012345.987'],
                ),
            ],
            creationDate: '2026-05-13T00:00:00.000Z',
        );

        $xml = $doc->render();

        $this->assertStringContainsString('<ObjectEvent>', $xml);
        $this->assertStringContainsString('<AggregationEvent>', $xml);
        $this->assertStringContainsString('<AssociationEvent>', $xml);
        $this->assertStringContainsString('<TransactionEvent>', $xml);
        $this->assertStringContainsString('<TransformationEvent>', $xml);
    }

    public function testTransformationEventIsDirectEventListChild(): void
    {
        $doc = new EPCISDocument(
            transformationEvents: [
                new TransformationEvent(eventTime: '2020-09-29T14:00:00.000+02:00'),
            ],
            creationDate: '2026-05-13T00:00:00.000Z',
        );

        $xml = $doc->render();
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xpath = new \DOMXPath($dom);

        // TransformationEvent must be a direct EventList child in 2.0 (not inside <extension>)
        $nodes = $xpath->query('//*[local-name()="EventList"]/*[local-name()="TransformationEvent"]');
        $this->assertEquals(1, $nodes->length);

        $extNodes = $xpath->query('//*[local-name()="EventList"]/*[local-name()="extension"]');
        $this->assertEquals(0, $extNodes->length);
    }

    public function testCbvmdaNamespaceDeclaredOnIlmdWhenUsed(): void
    {
        $doc = new EPCISDocument(
            objectEvents: [
                new ObjectEvent(
                    eventTime: '2025-10-14T14:11:35.000Z',
                    action: Action::Add,
                    ilmd: [
                        new InstanceLotMasterDataAttribute(InstanceLotMasterData::LotNumber, 'ABC123'),
                    ],
                ),
            ],
            creationDate: '2026-05-13T00:00:00.000Z',
        );

        $xml = $doc->render();
        $this->assertStringContainsString('xmlns:cbvmda="urn:epcglobal:cbv:mda"', $xml);
        $this->assertStringContainsString('cbvmda:lotNumber', $xml);
        $this->assertStringContainsString('ABC123', $xml);
    }

    public function testJsonStructure(): void
    {
        $doc = new EPCISDocument(
            objectEvents: [
                new ObjectEvent(
                    eventTime: '2025-10-14T14:11:35.000Z',
                    action: Action::Add,
                    bizStep: BusinessSteps::Commissioning,
                    epcList: ['urn:epc:id:sgtin:030003.0029328.100011869390'],
                ),
            ],
            creationDate: '2026-05-13T00:00:00.000Z',
        );

        $json = $doc->renderJson();

        $this->assertArrayHasKey('EPCISDocument', $json);
        $this->assertEquals('2.0', $json['EPCISDocument']['schemaVersion']);
        $this->assertArrayHasKey('EPCISBody', $json['EPCISDocument']);
        $this->assertArrayHasKey('EventList', $json['EPCISDocument']['EPCISBody']);
        $this->assertCount(1, $json['EPCISDocument']['EPCISBody']['EventList']);
        $this->assertArrayHasKey('objectEvent', $json['EPCISDocument']['EPCISBody']['EventList'][0]);
    }
}
