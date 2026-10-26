<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Production sheet {{ $card['order_number'] }}</title>
    <style>
        body { font: 18px/1.5 sans-serif; margin: 2rem; }
        h1, h2 { margin-bottom: .25rem; }
        @media print { body { font-size: 22px; margin: 1rem; } }
    </style>
</head>
<body>
    <h1>Order {{ $card['order_number'] }}</h1>
    <p>Item {{ $card['item_position']['number'] }} of {{ $card['item_position']['total'] }}</p>
    <p>Status: {{ $card['production_status'] }} — Due {{ $card['due_date'] }}</p>
    <h2>Look and materials</h2>
    <p>{{ $card['look'] }}</p>
    @foreach ($card['materials_check'] as $material)
        <p>{{ $material['name'] }}: {{ $material['available'] ? 'Available' : 'Unavailable' }}</p>
    @endforeach
    <h2>Personalization</h2>
    <p>{{ $card['text']['value'] ?? 'No approved text' }}</p>
    <p>{{ $card['customer_note'] }}</p>
    <h2>Checklist</h2>
    @foreach ($card['steps'] as $step)
        <p>{{ $step['done'] ? '[x]' : '[ ]' }} {{ $step['label'] }}</p>
    @endforeach
    <h2>Delivery</h2>
    <p>{{ $card['delivery']['recipient_name'] }} — {{ $card['delivery']['phone'] }}</p>
    <p>{{ $card['delivery']['address_line1'] }}, {{ $card['delivery']['city'] }}, {{ $card['delivery']['governorate'] }}</p>
    @if ($card['gift_wrap'])
        <p>Gift wrap</p>
    @endif
    <p>{{ $card['gift_message'] }}</p>
</body>
</html>
