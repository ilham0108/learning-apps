<?php

namespace App\Domain\Exams\Enums;

enum NavigationMode: string
{
    case Free = 'free';
    case ForwardOnly = 'forward_only';
}
