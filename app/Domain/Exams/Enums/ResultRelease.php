<?php

namespace App\Domain\Exams\Enums;

enum ResultRelease: string
{
    case Immediate = 'immediate';
    case AfterClose = 'after_close';
    case Manual = 'manual';
}
