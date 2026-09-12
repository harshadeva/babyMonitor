<?php

namespace App\Enums;

enum StoolConsistency: string
{
    case Seedy = 'seedy';
    case Pasty = 'pasty';
    case Watery = 'watery';
    case Hard = 'hard';
    case Mucousy = 'mucousy';
}
