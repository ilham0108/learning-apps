<?php

namespace App\Domain\Candidates\Enums;

enum MaritalStatus: string
{
    case Single = 'belum_menikah';
    case Married = 'menikah';
    case Divorced = 'cerai';
    case PreferNotToSay = 'tidak_disebutkan';
}
