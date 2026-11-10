<?php

namespace App\Domain\Exams\Enums;

enum ScorePolicy: string
{
    case Highest = 'highest';
    case Latest = 'latest';
}
