<?php

namespace App\Http\Controllers;

use App\Http\Requests\StudioEventBatchRequest;
use App\Models\StudioEvent;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudioEventController extends Controller
{
    public function store(StudioEventBatchRequest $request): JsonResponse
    {
        $events = collect($request->validated('events'))->map(fn (array $event): array => [
            'session_id' => $event['session_id'],
            'step' => $event['step'],
            'event' => $event['event'] ?? 'viewed',
            'created_at' => $event['created_at'] ?? now(),
        ])->all();
        StudioEvent::query()->insert($events);

        return ApiResponse::success(['accepted' => count($events)], 'Studio events recorded.', 202);
    }
}
