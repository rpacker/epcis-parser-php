<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\CBV;

enum Disposition: string
{
    case Active                  = 'urn:epcglobal:cbv:disp:active';
    case Inactive                = 'urn:epcglobal:cbv:disp:inactive';
    case InProgress              = 'urn:epcglobal:cbv:disp:in_progress';
    case InTransit               = 'urn:epcglobal:cbv:disp:in_transit';
    case Encoded                 = 'urn:epcglobal:cbv:disp:encoded';
    case Destroyed               = 'urn:epcglobal:cbv:disp:destroyed';
    case Dispensed               = 'urn:epcglobal:cbv:disp:dispensed';
    case Expired                 = 'urn:epcglobal:cbv:disp:expired';
    case NeedsReplacement        = 'urn:epcglobal:cbv:disp:needs_replacement';
    case NonConformant           = 'urn:epcglobal:cbv:disp:non_conformant';
    case Conformant              = 'urn:epcglobal:cbv:disp:conformant';
    case ContainerClosed         = 'urn:epcglobal:cbv:disp:container_closed';
    case ContainerOpen           = 'urn:epcglobal:cbv:disp:container_open';
    case Recalled                = 'urn:epcglobal:cbv:disp:recalled';
    case Reserved                = 'urn:epcglobal:cbv:disp:reserved';
    case Retail                  = 'urn:epcglobal:cbv:disp:retail_sold';
    case Returned                = 'urn:epcglobal:cbv:disp:returned';
    case Sellable                = 'urn:epcglobal:cbv:disp:sellable_accessible';
    case SellableNotAccessible   = 'urn:epcglobal:cbv:disp:sellable_not_accessible';
    case Stolen                  = 'urn:epcglobal:cbv:disp:stolen';
    case Unknown                 = 'urn:epcglobal:cbv:disp:unknown';
    // 2.0 additions
    case CompletenessInferred    = 'urn:epcglobal:cbv:disp:completeness_inferred';
    case CompletenessVerified    = 'urn:epcglobal:cbv:disp:completeness_verified';
}
