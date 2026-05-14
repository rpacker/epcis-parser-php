<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\CBV;

enum InstanceLotMasterData: string
{
    // Lot-level attributes
    case LotNumber          = 'lotNumber';
    case ItemExpirationDate = 'itemExpirationDate';
    case ProductionDate     = 'productionDate';
    case BestBeforeDate     = 'bestBeforeDate';
    case PackagingDate      = 'packagingDate';
    case FreezingDate       = 'freezingDate';
    case HarvestDate        = 'harvestDate';
    case CountryOfOrigin    = 'countryOfOrigin';

    // Trade item-level attributes
    case DescriptionShort   = 'descriptionShort';
    case AdditionalTradeItemIdentification = 'additionalTradeItemIdentification';
    case CountryOfOriginStatement = 'countryOfOriginStatement';
    case DosageFormType     = 'dosageFormType';
    case DrugSchedule       = 'drugSchedule';
    case MeasurementNetContent = 'measurementNetContent';
    case TradeItemDescription = 'tradeItemDescription';

    public function namespace(): string
    {
        return 'urn:epcglobal:cbv:mda';
    }

    public function prefix(): string
    {
        return 'cbvmda';
    }
}
