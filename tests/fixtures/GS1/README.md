# GS1 EPCIS Example Files

Source: https://ref.gs1.org/docs/epcis/examples/

This directory contains 81 official GS1 reference files covering the full EPCIS 2.0 standard: 33 XML, 47 JSON-LD, and 1 JSON.

> **Critical note**: All files here use **EPCIS 2.0** (`schemaVersion="2.0"`, namespace `urn:epcglobal:epcis:xsd:2`).  
> Our parser is built for **EPCIS 1.2** (`urn:epcglobal:epcis:xsd:1`).  
> See the [Support Status](#support-status-summary) section for what we can and cannot parse today.

---

## File Categories

### 1. Basic Event Types

The canonical one-of-each examples. Starting point for understanding each event type.

| File | What it shows | We handle? |
|------|---------------|-----------|
| `object_event.xml` | Two ObjectEvents: one with `epcList` + `bizTransactionList`, one with `quantityList` + `ilmd` + vendor extension elements | Partial — EPCs and bizStep parsed; `quantityList` at event level (not in `<extension>`) is EPCIS 2.0 and will be missed |
| `aggregation_event.xml` | One AggregationEvent with `childEPCs` and `childQuantityList` | Partial — childEPCs parsed; `childQuantityList` at event level missed |
| `transaction_event.xml` | TransactionEvent examples including healthcare (hospital discharge) and rail passage with domain-specific extensions | Partial — core fields parsed; domain extensions ignored |
| `transformation_event.xml` | TransformationEvent with `sensorElementList`, `errorDeclaration`, and `ilmd` | Partial — input/output EPCs and ILMD parsed; sensor data ignored |
| `association_event.xml` | AssociationEvent: attaches a sensor (GIAI) to a reusable asset (GRAI). EPCIS 2.0 only — does not exist in 1.2 | **No** — `AssociationEvent` is not in our parser |

---

### 2. All-Possible-Fields Examples

Each event type shown with every optional field populated. Useful as a completeness reference and for building test cases.

| File | What it shows | We handle? |
|------|---------------|-----------|
| `object_event_all_possible_fields.xml` | ObjectEvent with sensorElementList, persistentDisposition, sourceList, destinationList, errorDeclaration, quantityList, ilmd, and multiple vendor namespaces | Partial — core identity fields parsed; sensor data and persistentDisposition ignored |
| `object_event_all_possible_fields.jsonld` | Same content in JSON-LD | **No** — JSON-LD not supported |
| `aggregation_event_all_possible_fields.xml` | AggregationEvent with all EPCIS 2.0 optional fields | Partial |
| `aggregation_event_all_possible_fields.jsonld` | JSON-LD variant | **No** |
| `transaction_event_all_possible_fields.xml` | TransactionEvent with all fields | Partial |
| `transaction_event_all_possible_fields.jsonld` | JSON-LD variant | **No** |
| `transformation_event_all_possible_fields.xml` | TransformationEvent with all fields including full sensor data and ILMD | Partial |
| `transformation_event_all_possible_fields.jsonld` | JSON-LD variant | **No** |
| `association_event_all_possible_fields.xml` | AssociationEvent with all fields | **No** |
| `association_event_all_possible_fields.jsonld` | JSON-LD variant | **No** |

---

### 3. Sensor Data Examples

EPCIS 2.0 added `sensorElementList` to all event types, allowing IoT readings (temperature, humidity, shock, etc.) to be embedded alongside supply chain events. Each `sensorElement` has `sensorMetadata` (device, time window) and one or more `sensorReport` entries (type, value, unit of measure).

**None of these are supported** — our parser ignores `sensorElementList` entirely.

| File | Scenario |
|------|----------|
| `sensor_data_examples.xml` | XML document with 17 distinct sensor cases in a single file |
| `sensor_data_example1.jsonld` | Temperature, humidity, speed, illuminance readings at discrete time points |
| `sensor_data_example1b.jsonld` | Variant of example 1 with aggregated min/max/mean statistics |
| `sensor_data_example2.jsonld` | Storage condition monitoring with no object ID (location-only observation) |
| `sensor_data_example3.jsonld` | Sensor data on a TransformationEvent |
| `sensor_data_example4.jsonld` | `booleanValue` and `stringValue` sensor report types |
| `sensor_data_example5.jsonld` | `hexBinaryValue` sensor data |
| `sensor_data_example6.jsonld` | `uriValue` sensor report |
| `sensor_data_example7.jsonld` | Microorganism detection (`microorganism` field) |
| `sensor_data_example8.jsonld` | Chemical substance detection (`chemicalSubstance` field) |
| `sensor_data_example9.jsonld` | Percentage rank/value in sensor reports |
| `sensor_data_example10.jsonld` | Multiple sensor devices on one event |
| `sensor_data_example11.jsonld` | Sensor data with `dataProcessingMethod` reference |
| `sensor_data_example12.jsonld` | Sensor data with `bizRules` reference |
| `sensor_data_example13.jsonld` | Sensor with `rawData` and `deviceMetadata` URLs |
| `sensor_data_example14.jsonld` | Sensor data on AggregationEvent |
| `sensor_data_example15.jsonld` | Sensor data on TransactionEvent |
| `sensor_data_example16.jsonld` | Component-level sensor report (sub-sensor within device) |
| `sensor_data_example17.jsonld` | Sensor data with vendor extension fields |

---

### 4. Error Declaration and Corrective Events

EPCIS allows declaring that a previously submitted event contained an error and linking to a replacement event.

| File | What it shows | We handle? |
|------|---------------|-----------|
| `error_declaration_and_corrective_event.xml` | Two TransformationEvents: first has `errorDeclaration` with `reason` and `correctiveEventIDs` pointing to the second | Partial — our parser reads `declarationTime`, `reason`, and `correctiveEventIDs` but does not act on them (no automatic event invalidation) |
| `error_declaration_and_corrective_event.jsonld` | JSON-LD version of the same | **No** |

---

### 5. Event Hash / Deterministic Event ID Examples

EPCIS 2.0 defines a deterministic hash algorithm (CBV 2.0) that generates a reproducible `ni:///sha-256;...?ver=CBV2.0` event ID from event field content. These files demonstrate pairs or groups of events that are semantically identical and should produce the same hash.

**None of these are supported** — we do not implement or verify hash-based event IDs.

| File | What it shows |
|------|---------------|
| `event_with_identical_hash_id_1.xml` | Base ObjectEvent with full field set (sourceList, destinationList, sensorElement, persistentDisposition) used as hash reference |
| `event_with_identical_hash_id_2.xml` | Same event with fields in different XML ordering — should hash identically |
| `event_with_identical_hash_id_3.xml` | Same event with whitespace variations |
| `event_with_identical_hash_id_4.xml` | Same event with namespace prefix differences |
| `event_with_identical_hash_id_5.xml` | Same event with alternative timezone offset representation |
| `event_with_identical_hash_id_6.xml` | Same event with numeric precision variation |
| `event_with_identical_hash_id_7.json` | JSON representation of the same base event |

---

### 6. EPCIS 2.0 Specification Section 9.6 Examples

The official examples from the EPCIS 2.0 specification document, sections 9.6.1–9.6.4. Exist in both JSON-LD and XML to demonstrate format equivalence.

| File | Section | What it shows | We handle? |
|------|---------|---------------|-----------|
| `example_9.6.1-object_event.jsonld` | 9.6.1 | ObjectEvent (shipping + receiving) in JSON-LD | **No** |
| `example_9.6.1-object_event-2020_06_18a.xml` | 9.6.1 | Same events in EPCIS 2.0 XML | Partial — events parseable but schema version check would reject |
| `example_9.6.1-object_event-with-error-declaration.jsonld` | 9.6.1 | ObjectEvent with `errorDeclaration` in JSON-LD | **No** |
| `example_9.6.1-object_event-with-pseudo-SBDH-headers.jsonld` | 9.6.1 | JSON-LD with `sender`, `receiver`, `instanceIdentifier` fields (EPCIS 2.0 replaces SBDH with first-class document fields) | **No** |
| `example_9.6.1-with-comment.jsonld` | 9.6.1 | Same event with a JSON-LD `rdfs:comment` annotation | **No** |
| `example_9.6.2-object_event.jsonld` | 9.6.2 | ObjectEvent demonstrating class-level (quantity) identification | **No** |
| `example_9.6.2-object_event_with_gs1_digital_link.jsonld` | 9.6.2 | Same with GS1 Digital Link URIs (e.g. `https://id.gs1.org/01/...`) instead of EPC URNs | **No** |
| `example_9.6.3-aggregation_event.jsonld` | 9.6.3 | AggregationEvent in JSON-LD | **No** |
| `example_9.6.3-aggregation_event_with_gs1_digital_link.jsonld` | 9.6.3 | Same with Digital Link identifiers | **No** |
| `example_9.6.4-transformation_event.jsonld` | 9.6.4 | TransformationEvent in JSON-LD | **No** |
| `example_9.6.4-transformation_event_with_gs1_digital_link.jsonld` | 9.6.4 | Same with Digital Link identifiers | **No** |

---

### 7. GS1 Digital Link Variants

EPCIS 2.0 supports GS1 Digital Link URIs (`https://id.gs1.org/01/09521234543213/21/ABC`) as an alternative to EPC URNs (`urn:epc:id:sgtin:...`). The Digital Link encodes GTIN, serial, lot, and other application identifiers into a URL.

Referenced in the 9.6.x examples above. We have no support for parsing or generating Digital Link identifiers — `EpcHelper` only handles EPC URN format.

---

### 8. Core Business Vocabulary (CBV) Examples

Official examples from the CBV 2.0 specification demonstrating compliant identifier usage.

| File | What it shows | We handle? |
|------|---------------|-----------|
| `cbv-11.1-2020-06-16a.xml` | Single ObjectEvent (commissioning) using CBV-compliant bizStep, disposition, and EPC URIs | Partial — parseable structure; EPCIS 2.0 schema |
| `cbv-11.2-2020-06-16a.xml` | ObjectEvent with `PGLN` (party GLN) identifiers in sourceList/destinationList | Partial |
| `cbv-11.3-2020-06-16a.xml` | Multi-event document: commissioning + packing + shipping chain | Partial |
| `cbv-11.4-2020-06-16a.xml` | Receiving event with inspection disposition | Partial |

---

### 9. Real-World Domain Examples

| File | Domain | What it shows | We handle? |
|------|--------|---------------|-----------|
| `example-transaction_event-2020_07_03y.xml` | Healthcare / Rail | Two TransactionEvents: (1) hospital discharge summary identified by GDTI, patient by GSRN; (2) rail passage with train vehicle count and custom `rail:` namespace extension | Partial — core fields extracted; domain extensions and GSRN/GDTI identifiers not specifically handled |
| `example-transaction_event-2020_07_03y.jsonld` | Healthcare / Rail | Same in JSON-LD | **No** |

---

### 10. Persistent Disposition

EPCIS 2.0 added `persistentDisposition` — a long-term state that persists beyond a single event (e.g. `completeness_verified` stays set until explicitly unset). Separate from the event-level `disposition`.

| File | What it shows | We handle? |
|------|---------------|-----------|
| `example-persistent_disposition.xml` | AggregationEvents with `persistentDisposition/set` and `persistentDisposition/unset` elements tracking packing completeness | **No** — field not parsed or stored |
| `persistent_disposition-example.jsonld` | JSON-LD version | **No** |

---

### 11. EPCIS Capture Job (REST API)

EPCIS 2.0 defines a REST capture API. When a batch of events is submitted, the server returns an `EPCISCaptureJob` resource tracking the asynchronous processing state. These are not event documents — they are job status responses.

| File | What it shows | We handle? |
|------|---------------|-----------|
| `example-capture_job_success.xml` | Completed job: `running=false`, `success=true`, with `createdAt`/`finishedAt` timestamps | **No** — different root element, not an EPCISDocument |
| `example-capture_job_running.xml` | In-progress job: `running=true`, `success=false` | **No** |
| `example-capture_job_with_errors.xml` | Failed job with `captureErrorBehaviour` | **No** |
| `example-capture_job_with_error_file.xml` | Failed job with link to a separate error detail document | **No** |

---

### 12. Master Data

| File | What it shows | We handle? |
|------|---------------|-----------|
| `masterdata_all_possible_fields.xml` | `EPCISMasterData` with `VocabularyElement` attributes using all EPCIS 2.0 data types: `xsd:string`, `xsd:integer`, `xsd:double`, `xsd:dateTime`, `xsd:boolean`, nested objects, arrays, and three levels of nesting | Partial — our ingestion service reads `Location` and `EPCClass` vocabulary elements; does not handle all attribute data types or the `BusinessLocation` vocabulary type shown here |

---

### 13. EPCIS Query Document

| File | What it shows | We handle? |
|------|---------------|-----------|
| `epcis_query_document.jsonld` | `EPCISQueryDocument` — a query response wrapping event results with `subscriptionID` and `queryName`. Used by the EPCIS 2.0 query interface (SOAP or REST subscription) | **No** — different document type; we only ingest `EPCISDocument` |

---

### 14. Association Event Variants (JSON-LD)

Eight scenario-specific examples of `AssociationEvent` in JSON-LD, covering different use cases for attaching/detaching sensors or components from assets.

**None supported** — `AssociationEvent` and JSON-LD are both unsupported.

| File | Scenario |
|------|----------|
| `association_event-a.jsonld` | Attach sensor to reusable asset (ADD) |
| `association_event-b.jsonld` | Detach sensor from asset (DELETE) |
| `association_event-c.jsonld` | Observe existing sensor-asset relationship (OBSERVE) |
| `association_event-d.jsonld` | Sensor with multiple parent candidates |
| `association_event-e.jsonld` | Hierarchical asset association |
| `association_event-f.jsonld` | Association with quantity elements |
| `association_event-g.jsonld` | Association with sensor data |
| `association_event-h.jsonld` | Association with error declaration |
| `association_event.xml` | XML version of basic association |
| `association_event_examples.xml` | XML document with multiple association scenarios |
| `association_event_all_possible_fields.xml` | All optional fields populated |
| `association_event_all_possible_fields.jsonld` | JSON-LD version |

---

## Support Status Summary

### What our codebase handles today

| Capability | Status |
|-----------|--------|
| EPCIS 1.2 XML parsing | **Full** |
| EPCIS 2.0 XML parsing | **Partial** — FlexibleNSParser will extract events, but `quantityList` outside `<extension>`, `sensorElementList`, `persistentDisposition`, and `AssociationEvent` are silently ignored |
| ObjectEvent | **Full** (1.2); Partial (2.0) |
| AggregationEvent | **Full** (1.2); Partial (2.0) |
| TransactionEvent | **Full** (1.2); Partial (2.0) |
| TransformationEvent | **Full** (1.2); Partial (2.0) |
| AssociationEvent | **No** — EPCIS 2.0 only |
| JSON-LD format | **No** |
| Sensor data (`sensorElementList`) | **No** |
| Persistent disposition | **No** |
| GS1 Digital Link identifiers | **No** |
| Deterministic event hash IDs | **No** |
| EPCISCaptureJob responses | **No** |
| EPCISQueryDocument responses | **No** |
| EPCIS 2.0 short-form CBV values (`"shipping"` vs full URN) | **No** — parser stores the raw string; our CBV enums use full URNs |

### EPCIS 1.2 vs 2.0 key structural differences affecting parsing

| Feature | EPCIS 1.2 | EPCIS 2.0 |
|---------|-----------|-----------|
| XML namespace | `urn:epcglobal:epcis:xsd:1` | `urn:epcglobal:epcis:xsd:2` |
| `quantityList` location | Inside `<extension>` | At event level (no `<extension>` wrapper) |
| SBDH sender/receiver | Separate SBDH XML block | First-class `sender`, `receiver`, `instanceIdentifier` doc fields |
| Sensor data | Not present | `sensorElementList` on all event types |
| Persistent disposition | Not present | `persistentDisposition` on all event types |
| AssociationEvent | Not present | New event type |
| Event ID | Optional, free-form | Deterministic `ni:///sha-256;...` hash |
| JSON support | No | JSON-LD with `@context` |
| CBV identifiers | Full URNs required | Short names allowed (e.g. `"shipping"`) |
| Identifiers | EPC URN only | EPC URN + GS1 Digital Link |

### Roadmap to EPCIS 2.0 support

1. **Update XML namespace** in parser to also accept `urn:epcglobal:epcis:xsd:2`
2. **Move `quantityList` XPath** from `extension/quantityList/quantityElement` to also check `quantityList/quantityElement` directly
3. **Add `AssociationEvent`** class and handler in the parser
4. **Add `sensorElementList` parsing** and a `SensorElement`/`SensorReport` model
5. **Add `persistentDisposition` parsing** to the event models
6. **Add JSON-LD ingestion** — either a dedicated parser or conversion layer
7. **Add GS1 Digital Link** support to `EpcHelper`
8. **Support short-form CBV values** in bizStep/disposition parsing
