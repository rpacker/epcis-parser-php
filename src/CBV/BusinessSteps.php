<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\CBV;

enum BusinessSteps: string
{
    case Commissioning        = 'urn:epcglobal:cbv:bizstep:commissioning';
    case Decommissioning      = 'urn:epcglobal:cbv:bizstep:decommissioning';
    case Encoding             = 'urn:epcglobal:cbv:bizstep:encoding';
    case StageMoving          = 'urn:epcglobal:cbv:bizstep:stage_moving';
    case Stocking             = 'urn:epcglobal:cbv:bizstep:stocking';
    case Receiving            = 'urn:epcglobal:cbv:bizstep:receiving';
    case Shipping             = 'urn:epcglobal:cbv:bizstep:shipping';
    case Staging              = 'urn:epcglobal:cbv:bizstep:staging_outbound';
    case HoldRelease          = 'urn:epcglobal:cbv:bizstep:hold_release';
    case Packing              = 'urn:epcglobal:cbv:bizstep:packing';
    case Unpacking            = 'urn:epcglobal:cbv:bizstep:unpacking';
    case Loading              = 'urn:epcglobal:cbv:bizstep:loading';
    case Accepting            = 'urn:epcglobal:cbv:bizstep:accepting';
    case Arriving             = 'urn:epcglobal:cbv:bizstep:arriving';
    case Assembling           = 'urn:epcglobal:cbv:bizstep:assembling';
    case Collecting           = 'urn:epcglobal:cbv:bizstep:collecting';
    case CycleCount           = 'urn:epcglobal:cbv:bizstep:cycle_counting';
    case Departing            = 'urn:epcglobal:cbv:bizstep:departing';
    case Destroying           = 'urn:epcglobal:cbv:bizstep:destroying';
    case Disassembling        = 'urn:epcglobal:cbv:bizstep:disassembling';
    case Dispensing           = 'urn:epcglobal:cbv:bizstep:dispensing';
    case Entering             = 'urn:epcglobal:cbv:bizstep:entering_exiting';
    case Inspecting           = 'urn:epcglobal:cbv:bizstep:inspecting';
    case Installing           = 'urn:epcglobal:cbv:bizstep:installing';
    case Killing              = 'urn:epcglobal:cbv:bizstep:killing';
    case Maintaining          = 'urn:epcglobal:cbv:bizstep:maintaining';
    case Picking              = 'urn:epcglobal:cbv:bizstep:picking';
    case Relocating           = 'urn:epcglobal:cbv:bizstep:relocating';
    case Repackaging          = 'urn:epcglobal:cbv:bizstep:repackaging';
    case Repairing            = 'urn:epcglobal:cbv:bizstep:repairing';
    case Replacing            = 'urn:epcglobal:cbv:bizstep:replacing';
    case ReservingAllocation  = 'urn:epcglobal:cbv:bizstep:reserving';
    case RetailSelling        = 'urn:epcglobal:cbv:bizstep:retail_selling';
    case Sampling             = 'urn:epcglobal:cbv:bizstep:sampling';
    case SensorReporting      = 'urn:epcglobal:cbv:bizstep:sensor_reporting';
    case Storing              = 'urn:epcglobal:cbv:bizstep:storing';
    case Transporting         = 'urn:epcglobal:cbv:bizstep:transporting';
    case UnloadingTruck       = 'urn:epcglobal:cbv:bizstep:unloading';
    case VoidShipping         = 'urn:epcglobal:cbv:bizstep:void_shipping';
}
