# EPCPyYes PHP — EPCIS 2.0 Library

`serial-lab/epcpyyes-php` is a PHP 8.1+ library for generating and parsing GS1 EPCIS 2.0 documents. It produces schema-valid XML and JSON for all five EPCIS event types and covers the full EPCIS 2.0 feature set: `sensorElementList`, `persistentDisposition`, `AssociationEvent`, and top-level `ilmd`.

## Installation

```bash
composer require serial-lab/epcpyyes-php
```

No external PHP extensions required beyond the PHP standard library.

---

## Generating Events

All events share a common base. Named constructor parameters let you set only what you need.

### ObjectEvent

Used for commissioning, shipping, receiving, destroying individual items.

```php
use SerialLab\EPCPyYes\Events\ObjectEvent;
use SerialLab\EPCPyYes\Events\InstanceLotMasterDataAttribute;
use SerialLab\EPCPyYes\Events\QuantityElement;
use SerialLab\EPCPyYes\CBV\BusinessSteps;
use SerialLab\EPCPyYes\CBV\Disposition;

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
use SerialLab\EPCPyYes\Events\AggregationEvent;
use SerialLab\EPCPyYes\Events\Action;

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
use SerialLab\EPCPyYes\Events\AssociationEvent;

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
use SerialLab\EPCPyYes\Events\TransactionEvent;
use SerialLab\EPCPyYes\Events\BusinessTransaction;
use SerialLab\EPCPyYes\CBV\BusinessTransactionType;

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
use SerialLab\EPCPyYes\Events\TransformationEvent;

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
use SerialLab\EPCPyYes\Documents\EPCISDocument;

$doc = new EPCISDocument([$commissioningEvent, $shippingEvent]);

// Render to XML string
$xml = $doc->render();

// Render to array (for JSON serialization)
$array = $doc->renderToArray();
$json  = json_encode($array, JSON_PRETTY_PRINT);
```

The generated XML uses `xmlns:epcis="urn:epcglobal:epcis:xsd:2"` and `schemaVersion="2.0"`.

---

## Parsing EPCIS XML

`EPCISParser` accepts both EPCIS 1.2 and 2.0 documents. It uses namespace-agnostic XPath so it handles non-standard prefixes like the Cardinal Health `ns3:` format.

```php
use SerialLab\EPCPyYes\Parser\EPCISParser;

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
use SerialLab\EPCPyYes\Helpers\EpcHelper;

// From parts (companyPrefix + indicator + itemReference must total 13 digits)
$urn = EpcHelper::gtinToUrn('030003', '0', '029328', '100011869390');
// → urn:epc:id:sgtin:030003.0029328.100011869390

// From a 14-digit GTIN (indicator + company+item + check)
$urn = EpcHelper::gtin14ToSgtinUrn('00300030293282', '100011869390', companyPrefixLength: 6);
// → urn:epc:id:sgtin:030003.0029328.100011869390

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

// Current UTC time
[$time, $offset] = EpcHelper::getCurrentUtcTimeAndOffset();
```

---

## CBV Enumerations

All GS1 Core Business Vocabulary values are available as PHP 8.1 backed string enums. Pass them directly to event constructors — events also accept raw URN strings.

```php
use SerialLab\EPCPyYes\CBV\BusinessSteps;
use SerialLab\EPCPyYes\CBV\Disposition;
use SerialLab\EPCPyYes\CBV\SourceDestinationTypes;
use SerialLab\EPCPyYes\CBV\BusinessTransactionType;

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
use SerialLab\EPCPyYes\Events\SensorElement;
use SerialLab\EPCPyYes\Events\SensorMetadata;
use SerialLab\EPCPyYes\Events\SensorReport;

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
use SerialLab\EPCPyYes\Events\PersistentDisposition;

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
cd PHP
composer install
./vendor/bin/phpunit
```

33 tests, covering all five event types, the parser, and all EPC helper methods.
