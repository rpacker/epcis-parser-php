<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Parser;

use Rpacker\EpcisParser\Events\Action;
use Rpacker\EpcisParser\Events\AggregationEvent;
use Rpacker\EpcisParser\Events\AssociationEvent;
use Rpacker\EpcisParser\Events\BusinessTransaction;
use Rpacker\EpcisParser\Events\Destination;
use Rpacker\EpcisParser\Events\ErrorDeclaration;
use Rpacker\EpcisParser\Events\InstanceLotMasterDataAttribute;
use Rpacker\EpcisParser\Events\ObjectEvent;
use Rpacker\EpcisParser\Events\PersistentDisposition;
use Rpacker\EpcisParser\Events\QuantityElement;
use Rpacker\EpcisParser\Events\SensorElement;
use Rpacker\EpcisParser\Events\SensorMetadata;
use Rpacker\EpcisParser\Events\SensorReport;
use Rpacker\EpcisParser\Events\Source;
use Rpacker\EpcisParser\Events\TransactionEvent;
use Rpacker\EpcisParser\Events\TransformationEvent;

class EPCISParser
{
    protected \DOMDocument $dom;
    protected \DOMXPath $xpath;
    protected string $schemaVersion = '2.0';

    /**
     * @param string $xml the document itself — never a path or URL; use
     *                    fromFile() to read one from disk. (This used to
     *                    guess: input not starting with "<" was loaded as a
     *                    path, so a document with a UTF-8 byte-order mark
     *                    silently parsed to nothing, and untrusted content
     *                    naming a server file or URL got that file read.)
     *
     * @throws \InvalidArgumentException when $xml is not well-formed XML
     */
    public function __construct(string $xml)
    {
        $this->dom                     = new \DOMDocument();
        $this->dom->preserveWhiteSpace = false;

        $previous = libxml_use_internal_errors(true);
        try {
            // LIBXML_NONET: a document can't make the parser fetch anything.
            $loaded = trim($xml) !== '' && $this->dom->loadXML($xml, LIBXML_NONET);
            $error  = libxml_get_last_error();
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new \InvalidArgumentException('EPCIS input is not well-formed XML' . ($error ? ': ' . trim($error->message) : ''));
        }

        $this->xpath = new \DOMXPath($this->dom);
        $this->detectSchemaVersion();
    }

    /**
     * Parse a document from a local file.
     *
     * @throws \InvalidArgumentException when the file can't be read or isn't well-formed XML
     */
    public static function fromFile(string $path): static
    {
        $xml = is_file($path) ? file_get_contents($path) : false;
        if ($xml === false) {
            throw new \InvalidArgumentException("Cannot read EPCIS file: {$path}");
        }

        return new static($xml);
    }

    protected function detectSchemaVersion(): void
    {
        $root = $this->dom->documentElement;
        if ($root) {
            $sv = $root->getAttribute('schemaVersion');
            if ($sv !== '') {
                $this->schemaVersion = $sv;
            }
        }
    }

    public function parse(): void
    {
        foreach ($this->query('//*[local-name()="ObjectEvent"]') as $el) {
            $this->handleObjectEvent($this->parseObjectEvent($el));
        }
        foreach ($this->query('//*[local-name()="AggregationEvent"]') as $el) {
            $this->handleAggregationEvent($this->parseAggregationEvent($el));
        }
        foreach ($this->query('//*[local-name()="AssociationEvent"]') as $el) {
            $this->handleAssociationEvent($this->parseAssociationEventEl($el));
        }
        foreach ($this->query('//*[local-name()="TransactionEvent"]') as $el) {
            $this->handleTransactionEvent($this->parseTransactionEvent($el));
        }
        foreach ($this->query('//*[local-name()="TransformationEvent"]') as $el) {
            $this->handleTransformationEvent($this->parseTransformationEvent($el));
        }
    }

    protected function handleObjectEvent(ObjectEvent $event): void
    {
    }

    protected function handleAggregationEvent(AggregationEvent $event): void
    {
    }

    protected function handleAssociationEvent(AssociationEvent $event): void
    {
    }

    protected function handleTransactionEvent(TransactionEvent $event): void
    {
    }

    protected function handleTransformationEvent(TransformationEvent $event): void
    {
    }

    // -------------------------------------------------------------------------
    // Event parsers
    // -------------------------------------------------------------------------

    protected function parseObjectEvent(\DOMElement $el): ObjectEvent
    {
        $event = new ObjectEvent();
        $this->parseBaseFields($el, $event);
        $event->epcList                 = $this->parseEpcList($el, 'epcList');
        $event->action                  = $this->parseAction($el);
        $event->bizStep                 = $this->childText($el, 'bizStep');
        $event->disposition             = $this->childText($el, 'disposition');
        $event->readPoint               = $this->childIdText($el, 'readPoint');
        $event->bizLocation             = $this->childIdText($el, 'bizLocation');
        $event->businessTransactionList = $this->parseBizTransactions($el);
        $event->quantityList            = $this->parseQuantityList($el, 'quantityList');
        $event->sourceList              = $this->parseSources($el);
        $event->destinationList         = $this->parseDestinations($el);
        $event->sensorElementList       = $this->parseSensorElements($el);
        $event->persistentDisposition   = $this->parsePersistentDisposition($el);
        $event->ilmd                    = $this->parseIlmd($el);

        return $event;
    }

    protected function parseAggregationEvent(\DOMElement $el): AggregationEvent
    {
        $event = new AggregationEvent();
        $this->parseBaseFields($el, $event);
        $event->parentId                = $this->childText($el, 'parentID');
        $event->childEpcs               = $this->parseEpcList($el, 'childEPCs');
        $event->action                  = $this->parseAction($el);
        $event->bizStep                 = $this->childText($el, 'bizStep');
        $event->disposition             = $this->childText($el, 'disposition');
        $event->readPoint               = $this->childIdText($el, 'readPoint');
        $event->bizLocation             = $this->childIdText($el, 'bizLocation');
        $event->businessTransactionList = $this->parseBizTransactions($el);
        $event->childQuantityList       = $this->parseQuantityList($el, 'childQuantityList');
        $event->sourceList              = $this->parseSources($el);
        $event->destinationList         = $this->parseDestinations($el);
        $event->sensorElementList       = $this->parseSensorElements($el);
        $event->persistentDisposition   = $this->parsePersistentDisposition($el);

        return $event;
    }

    protected function parseAssociationEventEl(\DOMElement $el): AssociationEvent
    {
        $event = new AssociationEvent();
        $this->parseBaseFields($el, $event);
        $event->parentId                = $this->childText($el, 'parentID');
        $event->childEpcs               = $this->parseEpcList($el, 'childEPCs');
        $event->action                  = $this->parseAction($el);
        $event->bizStep                 = $this->childText($el, 'bizStep');
        $event->disposition             = $this->childText($el, 'disposition');
        $event->readPoint               = $this->childIdText($el, 'readPoint');
        $event->bizLocation             = $this->childIdText($el, 'bizLocation');
        $event->businessTransactionList = $this->parseBizTransactions($el);
        $event->childQuantityList       = $this->parseQuantityList($el, 'childQuantityList');
        $event->sourceList              = $this->parseSources($el);
        $event->destinationList         = $this->parseDestinations($el);
        $event->sensorElementList       = $this->parseSensorElements($el);
        $event->persistentDisposition   = $this->parsePersistentDisposition($el);

        return $event;
    }

    protected function parseTransactionEvent(\DOMElement $el): TransactionEvent
    {
        $event = new TransactionEvent();
        $this->parseBaseFields($el, $event);
        $event->businessTransactionList = $this->parseBizTransactions($el);
        $event->parentId                = $this->childText($el, 'parentID');
        $event->epcList                 = $this->parseEpcList($el, 'epcList');
        $event->action                  = $this->parseAction($el);
        $event->bizStep                 = $this->childText($el, 'bizStep');
        $event->disposition             = $this->childText($el, 'disposition');
        $event->readPoint               = $this->childIdText($el, 'readPoint');
        $event->bizLocation             = $this->childIdText($el, 'bizLocation');
        $event->quantityList            = $this->parseQuantityList($el, 'quantityList');
        $event->sourceList              = $this->parseSources($el);
        $event->destinationList         = $this->parseDestinations($el);
        $event->sensorElementList       = $this->parseSensorElements($el);
        $event->persistentDisposition   = $this->parsePersistentDisposition($el);

        return $event;
    }

    protected function parseTransformationEvent(\DOMElement $el): TransformationEvent
    {
        $event = new TransformationEvent();
        $this->parseBaseFields($el, $event);
        $event->inputEpcList            = $this->parseEpcList($el, 'inputEPCList');
        $event->inputQuantityList       = $this->parseQuantityList($el, 'inputQuantityList');
        $event->outputEpcList           = $this->parseEpcList($el, 'outputEPCList');
        $event->outputQuantityList      = $this->parseQuantityList($el, 'outputQuantityList');
        $event->transformationId        = $this->childText($el, 'transformationID');
        $event->bizStep                 = $this->childText($el, 'bizStep');
        $event->disposition             = $this->childText($el, 'disposition');
        $event->readPoint               = $this->childIdText($el, 'readPoint');
        $event->bizLocation             = $this->childIdText($el, 'bizLocation');
        $event->businessTransactionList = $this->parseBizTransactions($el);
        $event->sourceList              = $this->parseSources($el);
        $event->destinationList         = $this->parseDestinations($el);
        $event->sensorElementList       = $this->parseSensorElements($el);
        $event->persistentDisposition   = $this->parsePersistentDisposition($el);
        $event->ilmd                    = $this->parseIlmd($el);

        return $event;
    }

    // -------------------------------------------------------------------------
    // Field extractors
    // -------------------------------------------------------------------------

    protected function parseBaseFields(\DOMElement $el, object $event): void
    {
        $event->eventTime           = $this->childText($el, 'eventTime') ?? '';
        $event->eventTimezoneOffset = $this->childText($el, 'eventTimeZoneOffset') ?? '+00:00';
        $event->recordTime          = $this->childText($el, 'recordTime');
        // EPCIS 1.2 puts eventID and errorDeclaration under <baseExtension>.
        $base                    = $this->queryRelative($el, "*[local-name()='baseExtension']")->item(0);
        $event->eventId          = $this->childText($el, 'eventID') ?? ($base ? $this->childText($base, 'eventID') : null);
        $event->errorDeclaration = $this->parseErrorDeclaration($el) ?? ($base ? $this->parseErrorDeclaration($base) : null);
    }

    protected function parseAction(\DOMElement $el): Action|string
    {
        $text = $this->childText($el, 'action');

        return Action::tryFrom($text ?? '') ?? ($text ?? Action::Observe);
    }

    protected function parseEpcList(\DOMElement $el, string $listTag): array
    {
        $epcs = [];
        foreach ($this->queryRelative($el, ".//*[local-name()='{$listTag}']/*[local-name()='epc']") as $epcEl) {
            $epcs[] = trim($epcEl->textContent);
        }

        return $epcs;
    }

    protected function parseBizTransactions(\DOMElement $el): array
    {
        $result = [];
        foreach ($this->queryRelative($el, ".//*[local-name()='bizTransactionList']/*[local-name()='bizTransaction']") as $bt) {
            $result[] = new BusinessTransaction(
                $bt->getAttribute('type'),
                trim($bt->textContent),
            );
        }

        return $result;
    }

    protected function parseQuantityList(\DOMElement $el, string $tag): array
    {
        $result = [];
        // Look in current element and also inside <extension> for EPCIS 1.2 compat
        $nodes = $this->queryRelative($el, ".//*[local-name()='{$tag}']/*[local-name()='quantityElement']");
        foreach ($nodes as $qEl) {
            $epcClass = $this->childText($qEl, 'epcClass') ?? '';
            $qty      = (float) ($this->childText($qEl, 'quantity') ?? '0');
            $uom      = $this->childText($qEl, 'uom');
            $result[] = new QuantityElement($epcClass, $qty, $uom);
        }

        return $result;
    }

    protected function parseSources(\DOMElement $el): array
    {
        $result = [];
        foreach ($this->queryRelative($el, ".//*[local-name()='sourceList']/*[local-name()='source']") as $s) {
            $result[] = new Source($s->getAttribute('type'), trim($s->textContent));
        }

        return $result;
    }

    protected function parseDestinations(\DOMElement $el): array
    {
        $result = [];
        foreach ($this->queryRelative($el, ".//*[local-name()='destinationList']/*[local-name()='destination']") as $d) {
            $result[] = new Destination($d->getAttribute('type'), trim($d->textContent));
        }

        return $result;
    }

    protected function parseSensorElements(\DOMElement $el): array
    {
        $result = [];
        foreach ($this->queryRelative($el, ".//*[local-name()='sensorElementList']/*[local-name()='sensorElement']") as $seEl) {
            $meta    = null;
            $reports = [];

            foreach ($this->queryRelative($seEl, "*[local-name()='sensorMetadata']") as $mEl) {
                $meta = new SensorMetadata(
                    time:                 $mEl->getAttribute('time') ?: null,
                    deviceId:             $mEl->getAttribute('deviceID') ?: null,
                    deviceMetadata:       $mEl->getAttribute('deviceMetadata') ?: null,
                    rawData:              $mEl->getAttribute('rawData') ?: null,
                    startTime:            $mEl->getAttribute('startTime') ?: null,
                    endTime:              $mEl->getAttribute('endTime') ?: null,
                    bizRules:             $mEl->getAttribute('bizRules') ?: null,
                    dataProcessingMethod: $mEl->getAttribute('dataProcessingMethod') ?: null,
                );
            }

            foreach ($this->queryRelative($seEl, "*[local-name()='sensorReport']") as $rEl) {
                $reports[] = new SensorReport(
                    type:          $rEl->getAttribute('type') ?: null,
                    value:         $rEl->hasAttribute('value') ? (float) $rEl->getAttribute('value') : null,
                    uom:           $rEl->getAttribute('uom') ?: null,
                    minValue:      $rEl->hasAttribute('minValue') ? (float) $rEl->getAttribute('minValue') : null,
                    maxValue:      $rEl->hasAttribute('maxValue') ? (float) $rEl->getAttribute('maxValue') : null,
                    meanValue:     $rEl->hasAttribute('meanValue') ? (float) $rEl->getAttribute('meanValue') : null,
                    sDev:          $rEl->hasAttribute('sDev') ? (float) $rEl->getAttribute('sDev') : null,
                    percRank:      $rEl->hasAttribute('percRank') ? (float) $rEl->getAttribute('percRank') : null,
                    percValue:     $rEl->hasAttribute('percValue') ? (float) $rEl->getAttribute('percValue') : null,
                    chemicalSubstance: $rEl->getAttribute('chemicalSubstance') ?: null,
                    microorganism: $rEl->getAttribute('microorganism') ?: null,
                    deviceId:      $rEl->getAttribute('deviceID') ?: null,
                    deviceMetadata: $rEl->getAttribute('deviceMetadata') ?: null,
                    rawData:       $rEl->getAttribute('rawData') ?: null,
                    time:          $rEl->getAttribute('time') ?: null,
                    component:     $rEl->getAttribute('component') ?: null,
                    stringValue:   $rEl->getAttribute('stringValue') ?: null,
                    booleanValue:  $rEl->hasAttribute('booleanValue') ? ($rEl->getAttribute('booleanValue') === 'true') : null,
                    hexBinaryValue: $rEl->getAttribute('hexBinaryValue') ?: null,
                    uriValue:      $rEl->getAttribute('uriValue') ?: null,
                    dataProcessingMethod: $rEl->getAttribute('dataProcessingMethod') ?: null,
                );
            }

            if ($meta !== null) {
                $result[] = new SensorElement($meta, $reports);
            }
        }

        return $result;
    }

    protected function parsePersistentDisposition(\DOMElement $el): ?PersistentDisposition
    {
        $pdNodes = $this->queryRelative($el, "*[local-name()='persistentDisposition']");
        if ($pdNodes->length === 0) {
            return null;
        }
        $pdEl  = $pdNodes->item(0);
        $set   = [];
        $unset = [];
        foreach ($this->queryRelative($pdEl, "*[local-name()='set']") as $s) {
            $set[] = trim($s->textContent);
        }
        foreach ($this->queryRelative($pdEl, "*[local-name()='unset']") as $u) {
            $unset[] = trim($u->textContent);
        }

        return new PersistentDisposition($set, $unset);
    }

    protected function parseIlmd(\DOMElement $el): array
    {
        $result    = [];
        $ilmdNodes = $this->queryRelative($el, ".//*[local-name()='ilmd']");
        if ($ilmdNodes->length === 0) {
            return $result;
        }
        $ilmdEl = $ilmdNodes->item(0);
        foreach ($ilmdEl->childNodes as $child) {
            if (! ($child instanceof \DOMElement)) {
                continue;
            }
            $localName = $child->localName;
            $value     = trim($child->textContent);
            $result[]  = new InstanceLotMasterDataAttribute($localName, $value);
        }

        return $result;
    }

    protected function parseErrorDeclaration(\DOMElement $el): ?ErrorDeclaration
    {
        $nodes = $this->queryRelative($el, "*[local-name()='errorDeclaration']");
        if ($nodes->length === 0) {
            return null;
        }
        $edEl            = $nodes->item(0);
        $declarationTime = $this->childText($edEl, 'declarationTime') ?? '';
        $reason          = $this->childText($edEl, 'reason');
        $ids             = [];
        // Standard form wraps them in <correctiveEventIDs>; also accept them bare.
        $idsXpath = "*[local-name()='correctiveEventIDs']/*[local-name()='correctiveEventID'] | *[local-name()='correctiveEventID']";
        foreach ($this->queryRelative($edEl, $idsXpath) as $idEl) {
            $ids[] = trim($idEl->textContent);
        }

        return new ErrorDeclaration($declarationTime, $reason, $ids);
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    protected function childText(\DOMElement $el, string $localName): ?string
    {
        $nodes = $this->queryRelative($el, "*[local-name()='{$localName}']");
        if ($nodes->length === 0) {
            return null;
        }
        $text = trim($nodes->item(0)->textContent);

        return $text !== '' ? $text : null;
    }

    protected function childIdText(\DOMElement $el, string $localName): ?string
    {
        $nodes = $this->queryRelative($el, "*[local-name()='{$localName}']/*[local-name()='id']");
        if ($nodes->length === 0) {
            return null;
        }
        $text = trim($nodes->item(0)->textContent);

        return $text !== '' ? $text : null;
    }

    protected function query(string $xpathExpr): \DOMNodeList
    {
        return $this->xpath->query($xpathExpr);
    }

    protected function queryRelative(\DOMElement $context, string $xpathExpr): \DOMNodeList
    {
        return $this->xpath->query($xpathExpr, $context);
    }
}
