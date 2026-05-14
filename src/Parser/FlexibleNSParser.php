<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Parser;

/**
 * Handles EPCIS documents that use explicit namespace prefixes on event elements,
 * e.g. <ns3:ObjectEvent>, <epcis:AggregationEvent>, etc.
 * The base EPCISParser already uses local-name() XPath, so this class is identical
 * in behavior but provides a named subclass for consumers who want to distinguish
 * the two parsing modes in their code.
 */
class FlexibleNSParser extends EPCISParser
{
}
