<!DOCTYPE html>
<html lang="en">
<body>
    <h1>Your Giftopia piece is ready</h1>
    <p>Order number: {{ $orderNumber }}</p>
    <p>Balance due: {{ $balanceAmount }}</p>
    <p><a href="{{ $qcPhotoUrl }}">View the quality-check photo</a></p>
    <p><a href="{{ $frontendUrl }}/orders">Pay the balance</a></p>
</body>
</html>
