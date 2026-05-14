<?php

declare(strict_types=1);

namespace Rpacker\EpcisParser\Events;

enum Action: string
{
    case Add     = 'ADD';
    case Observe = 'OBSERVE';
    case Delete  = 'DELETE';
}
