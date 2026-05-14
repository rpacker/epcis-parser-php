<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\CBV;

enum SourceDestinationTypes: string
{
    case OwningParty      = 'urn:epcglobal:cbv:sdt:owning_party';
    case PossessingParty  = 'urn:epcglobal:cbv:sdt:possessing_party';
    case Location         = 'urn:epcglobal:cbv:sdt:location';
}
