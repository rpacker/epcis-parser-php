<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

use DOMDocument;
use DOMElement;

class AssociationEvent extends AggregationEvent
{
    public function renderFragment(DOMDocument $doc, DOMElement $parent): DOMElement
    {
        return $this->buildElement($doc, $parent, 'AssociationEvent');
    }

    protected function jsonKey(): string
    {
        return 'associationEvent';
    }
}
