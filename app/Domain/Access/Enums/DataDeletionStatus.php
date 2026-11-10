<?php

namespace App\Domain\Access\Enums;

enum DataDeletionStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Completed = 'completed';
}
