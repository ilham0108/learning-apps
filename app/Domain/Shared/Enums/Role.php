<?php

namespace App\Domain\Shared\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Hr = 'hr';
    case Candidate = 'candidate';
}
