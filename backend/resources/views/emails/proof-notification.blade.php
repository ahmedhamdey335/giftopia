<p>Hello {{ $order->shipping_name }},</p>
@if ($notificationType === 'requested')
    <p>Your personalization proof for order {{ $order->order_number }} is ready for review.</p>
@elseif ($notificationType === 'reminder')
    <p>Please review the personalization proof for order {{ $order->order_number }}.</p>
@elseif ($notificationType === 'auto_approved')
    <p>Your personalization proof for order {{ $order->order_number }} was automatically approved after the review period.</p>
@else
    <p>Your personalization proof for order {{ $order->order_number }} was approved and production can begin.</p>
@endif
<p>Proof version {{ $proof->version }}: “{{ $proof->text }}”</p>
<p><a href="{{ rtrim((string) config('app.frontend_url'), '/') }}/orders/{{ $order->id }}">View your order</a></p>
