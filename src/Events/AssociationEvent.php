<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

class AssociationEvent extends AggregationEvent
{
    public function renderFragment(\DOMDocument $doc, \DOMElement $parent): \DOMElement
    {
        return $this->buildElement($doc, $parent, 'AssociationEvent');
    }

    protected function childQuantityListBeforeAction(): bool
    {
        return true;
    }

    protected function jsonKey(): string
    {
        return 'associationEvent';
    }
}
