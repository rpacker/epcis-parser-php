<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\CBV;

enum BusinessTransactionType: string
{
    case Po       = 'urn:epcglobal:cbv:btt:po';
    case Desadv   = 'urn:epcglobal:cbv:btt:desadv';
    case Invoice  = 'urn:epcglobal:cbv:btt:inv';
    case Ra       = 'urn:epcglobal:cbv:btt:ra';
    case Rma      = 'urn:epcglobal:cbv:btt:rma';
    case Pedigree = 'urn:epcglobal:cbv:btt:pedigree';
    case Prodorder = 'urn:epcglobal:cbv:btt:prodorder';
    case Testprd  = 'urn:epcglobal:cbv:btt:testprd';
    case Testres  = 'urn:epcglobal:cbv:btt:testres';
    case Upevt    = 'urn:epcglobal:cbv:btt:upevt';
}
