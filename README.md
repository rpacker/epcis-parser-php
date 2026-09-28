# epcis-parser-php — EPCIS 2.0 / 1.2 Library

`rpacker/epcis-parser-php` is a PHP 8.1+ library for generating and parsing GS1 EPCIS 2.0 documents, and for writing EPCIS 1.2 (with SBDH, master data and the GS1 US DSCSA transaction statement) for trading partners that still exchange 1.2. It produces schema-valid XML and JSON for all five EPCIS event types and covers the full EPCIS 2.0 feature set: `sensorElementList`, `persistentDisposition`, `AssociationEvent`, and top-level `ilmd`.

## Installation

```bash
composer require rpacker/epcis-parser-php
```

No external PHP extensions required beyond the PHP standard library.

---

## Generating Events

All events share a common base. Named constructor parameters let you set only what you need.

### ObjectEvent

Used for commissioning, shipping, receiving, destroying individual items.

```php
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\InstanceLotMasterDataAttribute;
use Rpacker\EpcisParser\Events\QuantityElement;
use Rpacker\EpcisParser\CBV\BusinessSteps;
use Rpacker\EpcisParser\CBV\Disposition;

// Commissioning with ILMD
$event = new ObjectEvent(
    eventTime:            '2026-05-01T10:00:00.000Z',
    eventTimezoneOffset:  '-05:00',
    epcList: [
        'urn:epc:id:sgtin:030003.0029328.100011869390',
        'urn:epc:id:sgtin:030003.0029328.100011869391',
    ],
    bizStep:    BusinessSteps::Commissioning,
    disposition: Disposition::Active,
    readPoint:  'urn:epc:id:sgln:030003.0000000.0',
    ilmd: [
        new InstanceLotMasterDataAttribute('lotNumber',      'BATCH001', isCbv: true),
        new InstanceLotMasterDataAttribute('itemExpirationDate', '2028-06-30', isCbv: true),
    ],
);

// Shipping with quantity (bulk/uninventoried items)
$shipping = new ObjectEvent(
    bizStep:      BusinessSteps::Shipping,
    disposition:  Disposition::InTransit,
    readPoint:    'urn:epc:id:sgln:030003.0000000.0',
    quantityList: [
        new QuantityElement('urn:epc:class:lgtin:030003.0029328.BATCH001', 100, 'EA'),
    ],
);
```

### AggregationEvent

Used when items are packed into a container (case, pallet).

```php
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\Action;

$event = new AggregationEvent(
    action:     Action::Add,
    bizStep:    BusinessSteps::Packing,
    parentId:   'urn:epc:id:sscc:030003.0000000000001',
    childEpcs: [
        'urn:epc:id:sgtin:030003.0029328.100011869390',
        'urn:epc:id:sgtin:030003.0029328.100011869391',
    ],
);
```

### AssociationEvent

EPCIS 2.0 — associates items (e.g., drug + patient wristband) without a parent/child hierarchy.

```php
use Rpacker\EpcisParser\Events\AssociationEvent;

$event = new AssociationEvent(
    action:    Action::Add,
    bizStep:   BusinessSteps::Dispensing,
    parentId:  'urn:epc:id:sgtin:030003.0029328.100011869390',
    childEpcs: ['urn:epc:id:giai:030003.wristband-001'],
);
```

### TransactionEvent

Used when items are transferred with a business transaction (purchase order, invoice).

```php
use Rpacker\EpcisParser\Events\TransactionEvent;
use Rpacker\EpcisParser\Events\BusinessTransaction;
use Rpacker\EpcisParser\CBV\BusinessTransactionType;

$event = new TransactionEvent(
    action:   Action::Observe,
    bizStep:  BusinessSteps::Shipping,
    epcList: ['urn:epc:id:sgtin:030003.0029328.100011869390'],
    businessTransactionList: [
        new BusinessTransaction(
            BusinessTransactionType::Po->value,
            'urn:epcglobal:cbv:bt:0614141000005:PO-12345',
        ),
    ],
);
```

### TransformationEvent

Used when inputs are transformed into outputs (e.g., repackaging).

```php
use Rpacker\EpcisParser\Events\TransformationEvent;

$event = new TransformationEvent(
    bizStep:    BusinessSteps::Packing,
    inputEpcList: [
        'urn:epc:id:sgtin:030003.0029328.100011869390',
    ],
    outputEpcList: [
        'urn:epc:id:sgtin:030003.0029328.200011869390',
    ],
    transformationId: 'urn:uuid:' . bin2hex(random_bytes(8)),
);
```

---

## Building an EPCIS Document

`EPCISDocument` wraps one or more events into a schema-valid EPCIS 2.0 XML envelope.

```php
use Rpacker\EpcisParser\Documents\EPCISDocument;

$doc = new EPCISDocument([$commissioningEvent, $shippingEvent]);

// Render to XML string
$xml = $doc->render();

// Render to array (for JSON serialization)
$array = $doc->renderToArray();
$json  = json_encode($array, JSON_PRETTY_PRINT);
```

The generated XML uses `xmlns:epcis="urn:epcglobal:epcis:xsd:2"` and `schemaVersion="2.0"`.

---

## Building an EPCIS 1.2 Document

Most US DSCSA trading partners still exchange EPCIS 1.2. `EPCIS12Document` renders the same event objects as 1.2 XML (`urn:epcglobal:epcis:xsd:1`, `schemaVersion="1.2"`), with an optional header carrying the SBDH, master data, and industry header elements such as the GS1 US Healthcare DSCSA transaction statement. Events are written in the order given.

```php
use Rpacker\EpcisParser\CBV\VocabularyType;
use Rpacker\EpcisParser\Documents\EPCIS12Document;
use Rpacker\EpcisParser\Healthcare\DscsaTransactionStatement;
use Rpacker\EpcisParser\MasterData\Vocabulary;
use Rpacker\EpcisParser\MasterData\VocabularyElement;
use Rpacker\EpcisParser\SBDH\DocumentIdentification;
use Rpacker\EpcisParser\SBDH\Partner;
use Rpacker\EpcisParser\SBDH\StandardBusinessDocumentHeader;

$xml = (new EPCIS12Document(
    events: [$shippingEvent],
    sbdh: new StandardBusinessDocumentHeader(
        sender:    new Partner('Sender', 'urn:epc:id:sgln:0096295.00000.0', 'SGLN'),
        receivers: [new Partner('Receiver', 'urn:epc:id:sgln:110009452568..0', 'SGLN')],
        // InstanceIdentifier defaults to a random urn:uuid, CreationDateAndTime to the document's
        documentIdentification: new DocumentIdentification(),
    ),
    vocabularies: [
        new Vocabulary(VocabularyType::EpcClass, [
            VocabularyElement::cbv('urn:epc:idpat:sgtin:0360505.061326.*', [
                'additionalTradeItemIdentificationTypeCode' => 'FDA_NDC_11',
                'additionalTradeItemIdentification'         => '60505613206',
                'regulatedProductName'                      => 'OXALIPLATIN INJECTION',
            ]),
        ]),
        new Vocabulary(VocabularyType::Location, [
            VocabularyElement::cbv('urn:epc:id:sgln:110009452568..0', ['name' => 'ADVANCED RX PHARMACY 060', 'city' => 'NASHVILLE']),
        ]),
    ],
    headerElements: [
        new DscsaTransactionStatement('Seller has complied with each applicable subsection of FDCA Sec. 581(27)(A)-(G).'),
    ],
))->render();
```

What differs from 2.0, handled for you:

- Fields added after EPCIS 1.0 go under each event's `<extension>` (`quantityList`, `sourceList`, `destinationList`, `ilmd`, `childQuantityList`); `eventID` and `errorDeclaration` go under `<baseExtension>`.
- `TransformationEvent` is wrapped in `<extension>` inside the `EventList`.
- Master data sits in `<EPCISHeader><extension><EPCISMasterData>`.

EPCIS 2.0-only content — `AssociationEvent`, `sensorElementList`, `persistentDisposition` — has no 1.2 form, so rendering it throws `InvalidArgumentException` instead of silently dropping it. So does a `TransactionEvent` with no business transaction, which 1.2 requires.

Custom header content: implement `Documents\HeaderElement` (`render()` returns the element, `namespaces()` the prefixes to declare on the root).

---

## Parsing EPCIS XML

`EPCISParser` accepts both EPCIS 1.2 and 2.0 documents. It uses namespace-agnostic XPath so it handles non-standard prefixes like the Cardinal Health `ns3:` format.

The constructor takes the document's XML itself — never a path — and throws `InvalidArgumentException` if it isn't well-formed (a UTF-8 byte-order mark is fine). To read a file, use `EPCISParser::fromFile($path)`. Before 1.2.0 the constructor guessed: input not starting with `<` was loaded as a path, so a document with a byte-order mark parsed to nothing, and untrusted content naming a server file got that file read. Parsing never touches the network (`LIBXML_NONET`).

```php
use Rpacker\EpcisParser\Parser\EPCISParser;

$parser = new EPCISParser($xmlString);
$parser->parse();

// Detected schema version: '1.2' or '2.0'
echo $parser->schemaVersion;

// Events as structured arrays
foreach ($parser->events as $event) {
    echo $event['eventType'];   // ObjectEvent, AggregationEvent, etc.
    echo $event['eventTime'];
    echo $event['bizStep'];
    print_r($event['epcList']);
    print_r($event['quantityList']);
    print_r($event['ilmd']);        // ILMD attributes as key => value
    print_r($event['sensorElementList']); // EPCIS 2.0 sensor data
    print_r($event['persistentDisposition']); // EPCIS 2.0 persistent disposition
}
```

**EPCIS 1.2 compatibility:** The parser reads `quantityList`, `sourceList`, `destinationList`, and `ilmd` from both their EPCIS 1.2 `<extension>` wrappers and their EPCIS 2.0 top-level positions.

---

## EPC Helpers

`EpcHelper` builds GS1 EPC URNs without requiring GS1 membership lookups.

```php
use Rpacker\EpcisParser\Helpers\EpcHelper;

// From parts (companyPrefix + indicator + itemReference must total 13 digits)
$urn = EpcHelper::gtinToUrn('030003', '0', '029328', '100011869390');
// → urn:epc:id:sgtin:030003.0029328.100011869390

// From a 14-digit GTIN (indicator + company+item + check)
$urn = EpcHelper::gtin14ToSgtinUrn('00300030293282', '100011869390', companyPrefixLength: 6);
// → urn:epc:id:sgtin:030003.0029328.100011869390
```

The company prefix length is not encoded in a GTIN — it varies by company (US drug GTINs embed NDC labeler codes of different lengths: `030003` vs `0360505`). Take it from your product master data; guessing a fixed length yields well-formed URNs that match nothing your partners recorded. Serial numbers are percent-encoded per the GS1 Tag Data Standard (`/` → `%2F`, `&` → `%26`, …).

```php

// Generate URNs for a serial range
foreach (EpcHelper::gtinUrnGenerator('030003', '0', '029328', range(1, 100)) as $urn) {
    echo $urn . "\n";
}

// SSCC (pallet/case serial)
foreach (EpcHelper::ssccUrnGenerator('030003', [1, 2, 3]) as $sscc) {
    echo $sscc . "\n";
}

// SGLN (location)
$sgln = EpcHelper::gln13DataToSglnUrn('0614141', '00000', '0');
// → urn:epc:id:sgln:0614141.00000.0

// SGTIN class pattern (EPCClass master data id)
EpcHelper::gtin14ToSgtinPattern('00360505613267', 7);
// → urn:epc:idpat:sgtin:0360505.061326.*

// SSCC from a scanned 18-digit SSCC
EpcHelper::sscc18ToSsccUrn('003605050000001231', 7);
// → urn:epc:id:sscc:0360505.0000000123

// Validation: GS1 check digits
EpcHelper::normalizeGtin14('300030293282');       // → '00300030293282' (null if invalid)
EpcHelper::isValidSscc18('003605050000001231');    // → true
EpcHelper::checkDigit('0030003029328');            // → 2

// SGLN → company prefix / GLN-13 (with its real check digit, not the ".0" extension)
EpcHelper::sglnCompanyPrefix('urn:epc:id:sgln:0096295.00292.0'); // → '0096295'
EpcHelper::sglnToGln13('urn:epc:id:sgln:0096295.00292.0');       // → '0096295002928'

// Current UTC time
[$time, $offset] = EpcHelper::getCurrentUtcTimeAndOffset();
```

---

## CBV Enumerations

All GS1 Core Business Vocabulary values are available as PHP 8.1 backed string enums. Pass them directly to event constructors — events also accept raw URN strings.

```php
use Rpacker\EpcisParser\CBV\BusinessSteps;
use Rpacker\EpcisParser\CBV\Disposition;
use Rpacker\EpcisParser\CBV\SourceDestinationTypes;
use Rpacker\EpcisParser\CBV\BusinessTransactionType;

BusinessSteps::Commissioning->value;  // 'urn:epcglobal:cbv:bizstep:commissioning'
BusinessSteps::Shipping->value;       // 'urn:epcglobal:cbv:bizstep:shipping'
BusinessSteps::Receiving->value;      // 'urn:epcglobal:cbv:bizstep:receiving'
BusinessSteps::Packing->value;        // 'urn:epcglobal:cbv:bizstep:packing'
BusinessSteps::Destroying->value;     // 'urn:epcglobal:cbv:bizstep:destroying'
BusinessSteps::Dispensing->value;     // 'urn:epcglobal:cbv:bizstep:dispensing'

Disposition::Active->value;           // 'urn:epcglobal:cbv:disp:active'
Disposition::InTransit->value;        // 'urn:epcglobal:cbv:disp:in_transit'
Disposition::Destroyed->value;        // 'urn:epcglobal:cbv:disp:destroyed'

SourceDestinationTypes::OwningParty->value;     // 'urn:epcglobal:cbv:sdt:owning_party'
SourceDestinationTypes::PossessingParty->value; // 'urn:epcglobal:cbv:sdt:possessing_party'

BusinessTransactionType::Po->value;   // 'urn:epcglobal:cbv:btt:po'
BusinessTransactionType::Desadv->value; // 'urn:epcglobal:cbv:btt:desadv'
```

---

## EPCIS 2.0 Features

### Sensor Data

Attach IoT/sensor readings (temperature, humidity) to any event.

```php
use Rpacker\EpcisParser\Events\SensorElement;
use Rpacker\EpcisParser\Events\SensorMetadata;
use Rpacker\EpcisParser\Events\SensorReport;

$sensor = new SensorElement(
    metadata: new SensorMetadata(
        time:      '2026-05-01T10:00:00.000Z',
        deviceId:  'urn:epc:id:giai:030003.thermometer-1',
    ),
    reports: [
        new SensorReport(type: 'Temperature', value: 4.2, uom: 'CEL'),
        new SensorReport(type: 'Humidity',    value: 55.0, uom: 'A93'),
    ],
);

$event = new ObjectEvent(
    bizStep:          BusinessSteps::Stocking,
    sensorElementList: [$sensor],
);
```

### Persistent Disposition

EPCIS 2.0 — set/unset disposition flags that persist across events.

```php
use Rpacker\EpcisParser\Events\PersistentDisposition;

$event = new ObjectEvent(
    bizStep:              BusinessSteps::HoldRelease,
    persistentDisposition: new PersistentDisposition(
        set:   ['completeness_verified'],
        unset: ['in_progress'],
    ),
);
```

---

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

Covers all five event types, EPCIS 2.0 and 1.2 rendering, the parser, and all EPC helper methods.
