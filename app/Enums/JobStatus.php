<?php

namespace App\Enums;

enum JobStatus: string
{
    case Created = 'CREATED';
    case Assigned = 'ASSIGNED';
    case Accepted = 'ACCEPTED';
    case EnRoute = 'EN_ROUTE';
    case Reached = 'REACHED';
    case Inspected = 'INSPECTED';
    case Authorized = 'AUTHORIZED';
    case InProgress = 'IN_PROGRESS';
    case AwaitingPayment = 'AWAITING_PAYMENT';
    case PaymentPending = 'PAYMENT_PENDING';
    case Completed = 'COMPLETED';
    case Verified = 'VERIFIED';
    case Closed = 'CLOSED';
    case Cancelled = 'CANCELLED';
}
