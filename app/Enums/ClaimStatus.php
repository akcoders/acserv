<?php

namespace App\Enums;

enum ClaimStatus: string
{
    case Submitted = 'SUBMITTED';
    case UnderReview = 'UNDER_REVIEW';
    case Approved = 'APPROVED';
    case PartiallyApproved = 'PARTIALLY_APPROVED';
    case Rejected = 'REJECTED';
    case Closed = 'CLOSED';
}
