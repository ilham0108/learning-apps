<?php

namespace App\Domain\Learning\Enums;

enum ProgramStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
