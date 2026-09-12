<?php

namespace App\Enums;

enum TemperatureMethod: string
{
    case Rectal = 'rectal';
    case Oral = 'oral';
    case Armpit = 'armpit';
    case Forehead = 'forehead';
    case Ear = 'ear';
}
