<?php

namespace App\Enums;

enum RefundRequestStatus: string
{
    case Requested = 'requested';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Refunded = 'refunded';
    case Failed = 'failed';
}
