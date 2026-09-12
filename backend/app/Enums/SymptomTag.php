<?php

namespace App\Enums;

enum SymptomTag: string
{
    case Fussy = 'fussy';
    case SpitUp = 'spit_up';
    case Rash = 'rash';
    case JaundiceLook = 'jaundice_look';
    case Congestion = 'congestion';
    case Other = 'other';
}
