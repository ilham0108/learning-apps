<?php

namespace App\Domain\Candidates\Enums;

enum Recommendation: string
{
    case StrongYes = 'strong_yes';
    case Yes = 'yes';
    case Consider = 'consider';
    case No = 'no';
}
