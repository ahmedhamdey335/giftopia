<p>Hello {{ $order->shipping_name }},</p>
@if ($notice === 'balance_received')
    <p>We received the balance for order {{ $order->order_number }}.</p>
@elseif ($notice === 'out_for_delivery')
    <p>Order {{ $order->order_number }} is out for delivery.</p>
@else
    <p>Order {{ $order->order_number }} has been delivered.</p>
@endif
<p><a href="{{ rtrim((string) config('app.frontend_url'), '/') }}/orders/{{ $order->id }}">View your order</a></p>
