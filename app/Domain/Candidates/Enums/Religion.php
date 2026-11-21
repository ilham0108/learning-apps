<?php

namespace App\Domain\Candidates\Enums;

/**
 * Agama sesuai pilihan yang diakui di Indonesia, termasuk Kepercayaan terhadap
 * Tuhan YME. Kandidat boleh memilih tidak menyebutkan.
 */
enum Religion: string
{
    case Islam = 'islam';
    case Protestant = 'kristen';
    case Catholic = 'katolik';
    case Hindu = 'hindu';
    case Buddhist = 'buddha';
    case Confucian = 'konghucu';
    case Belief = 'kepercayaan';
    case PreferNotToSay = 'tidak_disebutkan';
}
