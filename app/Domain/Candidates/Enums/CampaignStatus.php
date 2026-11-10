<?php

namespace App\Domain\Candidates\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
}
