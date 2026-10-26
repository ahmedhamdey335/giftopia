<?php

namespace App\Enums;

enum ProductionEventType: string
{
    case Placed = 'placed';
    case DepositReceived = 'deposit_received';
    case PaymentFailed = 'payment_failed';
    case Cancelled = 'cancelled';
    case StatusChanged = 'status_changed';
    case ProofRequested = 'proof_requested';
    case ProofApproved = 'proof_approved';
    case ProofChangesRequested = 'proof_changes_requested';
    case BalanceRequested = 'balance_requested';
    case BalanceReceived = 'balance_received';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case RefundRequested = 'refund_requested';
    case RefundDecided = 'refund_decided';
}
