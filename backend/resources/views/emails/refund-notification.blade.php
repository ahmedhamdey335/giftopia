<p>{{ $staff ? 'A customer submitted a refund request.' : 'Your refund request has been updated.' }}</p>
<p>Order {{ $refund->order->order_number }}: {{ $refund->status->value }}</p>
<p>{{ $refund->reason }}</p>
<p>{{ $refund->decision_note }}</p>
