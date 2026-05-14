<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\CBV;

enum ErrorReason: string
{
    case DidNotOccur   = 'urn:epcglobal:cbv:er:did_not_occur';
    case IncorrectData = 'urn:epcglobal:cbv:er:incorrect_data';
}
