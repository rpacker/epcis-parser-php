<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Tests;

use PHPUnit\Framework\TestCase;
use Rpacker\EpcisParser\CBV\BusinessSteps;
use Rpacker\EpcisParser\CBV\Disposition;
use Rpacker\EpcisParser\CBV\InstanceLotMasterData;
use Rpacker\EpcisParser\CBV\SourceDestinationTypes;
use Rpacker\EpcisParser\CBV\VocabularyType;
use Rpacker\EpcisParser\Documents\EPCIS12Document;
use Rpacker\EpcisParser\Events\Action;
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\AssociationEvent;
use Rpacker\EpcisParser\Events\BusinessTransaction;
use Rpacker\EpcisParser\Events\Destination;
use Rpacker\EpcisParser\Events\ErrorDeclaration;
use Rpacker\EpcisParser\Events\InstanceLotMasterDataAttribute;
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\PersistentDisposition;
use Rpacker\EpcisParser\Events\Source;
use Rpacker\EpcisParser\Events\TransactionEvent;
use Rpacker\EpcisParser\Events\TransformationEvent;
use Rpacker\EpcisParser\Healthcare\DscsaTransactionStatement;
use Rpacker\EpcisParser\MasterData\Vocabulary;
use Rpacker\EpcisParser\MasterData\VocabularyElement;
use Rpacker\EpcisParser\Parser\EPCISParser;
use Rpacker\EpcisParser\SBDH\DocumentIdentification;
use Rpacker\EpcisParser\SBDH\Partner;
use Rpacker\EpcisParser\SBDH\StandardBusinessDocumentHeader;

class EPCIS12DocumentTest extends TestCase
{
    private const SENDER   = 'urn:epc:id:sgln:0096295.00000.0';
    private const RECEIVER = 'urn:epc:id:sgln:110009452568..0';

    private function shippingEvent(): ObjectEvent
    {
        return new ObjectEvent(
            eventTime: '2026-04-27T07:24:51.000Z',
            eventTimezoneOffset: '-04:00',
            epcList: ['urn:epc:id:sgtin:0360505.061326.589901'],
            action: Action::Observe,
            bizStep: BusinessSteps::Shipping,
            disposition: Disposition::InTransit,
            readPoint: 'urn:epc:id:sgln:0096295.00292.0',
            businessTransactionList: [
                new BusinessTransaction('urn:epcglobal:cbv:btt:po', 'urn:epcglobal:cbv:bt:1100094525683:12397'),
            ],
            sourceList: [new Source(SourceDestinationTypes::OwningParty->value, self::SENDER)],
            destinationList: [new Destination(SourceDestinationTypes::OwningParty->value, self::RECEIVER)],
            ilmd: [
                new InstanceLotMasterDataAttribute(InstanceLotMasterData::LotNumber, 'EB1W5016A'),
                new InstanceLotMasterDataAttribute(InstanceLotMasterData::ItemExpirationDate, '2027-09-30'),
            ],
        );
    }

    private function sbdh(): StandardBusinessDocumentHeader
    {
        return new StandardBusinessDocumentHeader(
            sender: new Partner('Sender', self::SENDER, 'SGLN'),
            receivers: [new Partner('Receiver', self::RECEIVER, 'SGLN')],
            documentIdentification: new DocumentIdentification(instanceIdentifier: 'urn:uuid:d1b64eac-ce6a-4bbd-8d2f-a8b7daa22451'),
        );
    }

    private function xpath(string $xml): \DOMXPath
    {
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));
        $x = new \DOMXPath($dom);
        $x->registerNamespace('epcis', EPCIS12Document::NS_EPCIS);
        $x->registerNamespace('sbdh', EPCIS12Document::NS_SBDH);
        $x->registerNamespace('cbvmda', EPCIS12Document::NS_CBVMDA);
        $x->registerNamespace('gs1ushc', DscsaTransactionStatement::NS);

        return $x;
    }

    /** @return string[] local names of an element's children, in order */
    private function childNames(\DOMXPath $x, string $path): array
    {
        return array_map(fn ($n) => $n->localName, iterator_to_array($x->query("{$path}/*")));
    }

    public function testRootIs12(): void
    {
        $xml = (new EPCIS12Document(creationDate: '2026-05-13T00:00:00.000Z'))->render();
        $x   = $this->xpath($xml);

        $this->assertSame('1.2', $x->evaluate('string(/epcis:EPCISDocument/@schemaVersion)'));
        $this->assertSame('2026-05-13T00:00:00.000Z', $x->evaluate('string(/epcis:EPCISDocument/@creationDate)'));
        $this->assertStringNotContainsString('xsd:2', $xml);
        $this->assertSame(0, $x->query('/epcis:EPCISDocument/EPCISHeader')->length, 'no header when there is nothing to put in it');
    }

    public function testObjectEventUses12Layout(): void
    {
        $x     = $this->xpath((new EPCIS12Document([$this->shippingEvent()]))->render());
        $event = '/epcis:EPCISDocument/EPCISBody/EventList/ObjectEvent';

        $this->assertSame(
            ['eventTime', 'eventTimeZoneOffset', 'epcList', 'action', 'bizStep', 'disposition', 'readPoint', 'bizTransactionList', 'extension'],
            $this->childNames($x, $event),
        );
        $this->assertSame(['sourceList', 'destinationList', 'ilmd'], $this->childNames($x, "{$event}/extension"));
        $this->assertSame('EB1W5016A', $x->evaluate("string({$event}/extension/ilmd/cbvmda:lotNumber)"));
        $this->assertSame(self::RECEIVER, $x->evaluate("string({$event}/extension/destinationList/destination)"));
    }

    public function testHeaderCarriesSbdhMasterDataAndHeaderElementsInOrder(): void
    {
        $doc = new EPCIS12Document(
            events: [$this->shippingEvent()],
            sbdh: $this->sbdh(),
            vocabularies: [
                new Vocabulary(VocabularyType::EpcClass, [
                    VocabularyElement::cbv('urn:epc:idpat:sgtin:0360505.061326.*', [
                        'additionalTradeItemIdentificationTypeCode' => 'FDA_NDC_11',
                        'additionalTradeItemIdentification'         => '60505613206',
                        'regulatedProductName'                      => 'OXALIPLATIN INJECTION',
                        'dosageFormType'                            => null,
                    ]),
                ]),
                new Vocabulary(VocabularyType::Location, [
                    VocabularyElement::cbv(self::RECEIVER, ['name' => 'ADVANCED RX PHARMACY 060', 'city' => 'NASHVILLE']),
                ]),
                new Vocabulary(VocabularyType::BusinessLocation, []),
            ],
            headerElements: [new DscsaTransactionStatement('Seller has complied with each applicable subsection of FDCA Sec. 581(27)(A)-(G).')],
            creationDate: '2026-04-27T02:26:21.873Z',
        );
        $x      = $this->xpath($doc->render());
        $header = '/epcis:EPCISDocument/EPCISHeader';

        $this->assertSame(['StandardBusinessDocumentHeader', 'extension', 'dscsaTransactionStatement'], $this->childNames($x, $header));

        $sbdh = "{$header}/sbdh:StandardBusinessDocumentHeader";
        $this->assertSame(['HeaderVersion', 'Sender', 'Receiver', 'DocumentIdentification'], $this->childNames($x, $sbdh));
        $this->assertSame(self::SENDER, $x->evaluate("string({$sbdh}/sbdh:Sender/sbdh:Identifier[@Authority='SGLN'])"));
        $this->assertSame(self::RECEIVER, $x->evaluate("string({$sbdh}/sbdh:Receiver/sbdh:Identifier)"));
        $this->assertSame('urn:uuid:d1b64eac-ce6a-4bbd-8d2f-a8b7daa22451', $x->evaluate("string({$sbdh}/sbdh:DocumentIdentification/sbdh:InstanceIdentifier)"));
        $this->assertSame('2026-04-27T02:26:21.873Z', $x->evaluate("string({$sbdh}/sbdh:DocumentIdentification/sbdh:CreationDateAndTime)"), 'defaults to the document creation date');

        $vocab = "{$header}/extension/EPCISMasterData/VocabularyList/Vocabulary";
        $this->assertSame(2, $x->query($vocab)->length, 'empty vocabularies are left out');
        $this->assertSame('60505613206', $x->evaluate("string({$vocab}[@type='urn:epcglobal:epcis:vtype:EPCClass']//attribute[@id='urn:epcglobal:cbv:mda#additionalTradeItemIdentification'])"));
        $this->assertSame(0, $x->query("{$vocab}//attribute[@id='urn:epcglobal:cbv:mda#dosageFormType']")->length, 'null attributes are skipped');
        $this->assertSame('NASHVILLE', $x->evaluate("string({$vocab}[@type='urn:epcglobal:epcis:vtype:Location']/VocabularyElementList/VocabularyElement[@id='" . self::RECEIVER . "']/attribute[@id='urn:epcglobal:cbv:mda#city'])"));

        $this->assertSame('true', $x->evaluate("string({$header}/gs1ushc:dscsaTransactionStatement/gs1ushc:affirmTransactionStatement)"));
    }

    public function testInstanceIdentifierIsGeneratedWhenNotGiven(): void
    {
        $sbdh = new StandardBusinessDocumentHeader(
            sender: new Partner('Sender', self::SENDER, 'SGLN'),
            receivers: [new Partner('Receiver', self::RECEIVER, 'SGLN')],
            documentIdentification: new DocumentIdentification(),
        );
        $x = $this->xpath((new EPCIS12Document(sbdh: $sbdh))->render());

        $this->assertMatchesRegularExpression(
            '/^urn:uuid:[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $x->evaluate('string(//sbdh:InstanceIdentifier)'),
        );
    }

    public function testAggregationTransactionAndTransformationEvents(): void
    {
        $doc = new EPCIS12Document([
            new AggregationEvent(
                eventTime: '2026-04-27T07:24:49.000Z',
                action: Action::Add,
                bizStep: BusinessSteps::Packing,
                readPoint: 'urn:epc:id:sgln:0096295.00292.0',
                parentId: 'urn:epc:id:sscc:0096295.4805053808',
                childEpcs: ['urn:epc:id:sgtin:0360505.061326.589901'],
            ),
            new TransactionEvent(
                eventTime: '2026-04-27T07:24:50.000Z',
                action: Action::Add,
                businessTransactionList: [new BusinessTransaction('urn:epcglobal:cbv:btt:po', 'urn:epcglobal:cbv:bt:1100094525683:12397')],
                epcList: ['urn:epc:id:sscc:0096295.4805053808'],
            ),
            new TransformationEvent(
                eventTime: '2026-04-27T07:24:52.000Z',
                inputEpcList: ['urn:epc:id:sgtin:0360505.061326.589901'],
                outputEpcList: ['urn:epc:id:sgtin:0360505.061326.600001'],
                bizStep: BusinessSteps::Repackaging,
            ),
        ]);
        $x    = $this->xpath($doc->render());
        $list = '/epcis:EPCISDocument/EPCISBody/EventList';

        $this->assertSame(['eventTime', 'eventTimeZoneOffset', 'parentID', 'childEPCs', 'action', 'bizStep', 'readPoint'], $this->childNames($x, "{$list}/AggregationEvent"));
        $this->assertSame(['eventTime', 'eventTimeZoneOffset', 'bizTransactionList', 'epcList', 'action'], $this->childNames($x, "{$list}/TransactionEvent"));
        $this->assertSame(1, $x->query("{$list}/extension/TransformationEvent")->length, 'TransformationEvent is wrapped in <extension> in 1.2');
        $this->assertSame(['eventTime', 'eventTimeZoneOffset', 'inputEPCList', 'outputEPCList', 'bizStep'], $this->childNames($x, "{$list}/extension/TransformationEvent"));
    }

    public function testEventIdAndErrorDeclarationGoUnderBaseExtension(): void
    {
        $event = new ObjectEvent(
            eventTime: '2026-04-27T07:24:51.000Z',
            eventId: 'urn:uuid:6e84ef43-8a3f-4ce6-b5f3-bb86e0f4b4e8',
            errorDeclaration: new ErrorDeclaration('2026-04-28T00:00:00.000Z', 'urn:epcglobal:cbv:er:incorrect_data', ['urn:uuid:404d95fc-9457-4a51-bd6a-0bba133845a8']),
            action: Action::Observe,
            epcList: ['urn:epc:id:sgtin:0360505.061326.589901'],
        );
        $x    = $this->xpath((new EPCIS12Document([$event]))->render());
        $base = '//ObjectEvent/baseExtension';

        $this->assertSame(['eventTime', 'eventTimeZoneOffset', 'baseExtension', 'epcList', 'action'], $this->childNames($x, '//ObjectEvent'));
        $this->assertSame('urn:uuid:6e84ef43-8a3f-4ce6-b5f3-bb86e0f4b4e8', $x->evaluate("string({$base}/eventID)"));
        $this->assertSame('urn:uuid:404d95fc-9457-4a51-bd6a-0bba133845a8', $x->evaluate("string({$base}/errorDeclaration/correctiveEventIDs/correctiveEventID)"));
    }

    public function testTextIsEscaped(): void
    {
        $event = new ObjectEvent(
            action: Action::Observe,
            epcList: ['urn:epc:id:sgtin:0360505.061326.589901'],
            businessTransactionList: [new BusinessTransaction('urn:epcglobal:cbv:btt:po', 'urn:epcglobal:cbv:bt:1100094525683:A&B<1>')],
        );
        $x = $this->xpath((new EPCIS12Document([$event]))->render());

        $this->assertSame('urn:epcglobal:cbv:bt:1100094525683:A&B<1>', $x->evaluate('string(//bizTransaction)'));
    }

    public function testRoundTripsThroughTheParser(): void
    {
        $xml = (new EPCIS12Document([$this->shippingEvent()], sbdh: $this->sbdh()))->render();

        $parser = new class($xml) extends EPCISParser {
            public array $events = [];

            protected function handleObjectEvent(ObjectEvent $event): void
            {
                $this->events[] = $event;
            }
        };
        $parser->parse();

        $this->assertCount(1, $parser->events);
        $event = $parser->events[0];
        $this->assertSame(['urn:epc:id:sgtin:0360505.061326.589901'], $event->epcList);
        $this->assertSame(self::SENDER, $event->sourceList[0]->source);
        $this->assertSame(self::RECEIVER, $event->destinationList[0]->destination);
        $this->assertSame(['EB1W5016A', '2027-09-30'], array_map(fn ($a) => $a->value, $event->ilmd));
    }

    public function testTransactionEventWithoutABusinessTransactionIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one business transaction');

        (new EPCIS12Document([new TransactionEvent(action: Action::Add, epcList: ['urn:epc:id:sgtin:0360505.061326.589901'])]))->render();
    }

    public function testAssociationEventIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('AssociationEvent is EPCIS 2.0-only');

        (new EPCIS12Document([new AssociationEvent(parentId: 'urn:epc:id:grai:4012345.55555.987', childEpcs: ['urn:epc:id:giai:4000001.12345'])]))->render();
    }

    public function testPersistentDispositionIsRejectedRatherThanDropped(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('persistentDisposition is EPCIS 2.0-only');

        (new EPCIS12Document([new ObjectEvent(
            epcList: ['urn:epc:id:sgtin:0360505.061326.589901'],
            persistentDisposition: new PersistentDisposition(set: ['urn:epcglobal:cbv:disp:damaged']),
        )]))->render();
    }
}
