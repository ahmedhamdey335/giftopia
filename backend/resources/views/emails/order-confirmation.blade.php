<!DOCTYPE html>
<html lang="en">
<body>
    <h1>Thank you for your Giftopia order</h1>
    <p>Order number: {{ $orderNumber }}</p>
    <p>Your order includes:</p>
    <ul>
        @foreach ($items as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
    <p>Total: {{ $total }}</p>
    <p>Ready by: {{ $readyDate }}</p>
    <p>{{ $handmadeVariationNote }}</p>
</body>
</html>
