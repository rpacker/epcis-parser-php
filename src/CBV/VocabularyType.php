<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\CBV;

enum VocabularyType: string
{
    case EpcClass                = 'urn:epcglobal:epcis:vtype:EPCClass';
    case Location                = 'urn:epcglobal:epcis:vtype:Location';
    case ReadPoint               = 'urn:epcglobal:epcis:vtype:ReadPoint';
    case BusinessLocation        = 'urn:epcglobal:epcis:vtype:BusinessLocation';
    case BusinessStep            = 'urn:epcglobal:epcis:vtype:BusinessStep';
    case Disposition             = 'urn:epcglobal:epcis:vtype:Disposition';
    case BusinessTransaction     = 'urn:epcglobal:epcis:vtype:BusinessTransaction';
    case BusinessTransactionType = 'urn:epcglobal:epcis:vtype:BusinessTransactionType';
    case SourceDestType          = 'urn:epcglobal:epcis:vtype:SourceDestType';
}
