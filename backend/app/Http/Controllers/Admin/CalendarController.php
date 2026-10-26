<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlackoutDateRequest;
use App\Models\BlackoutDate;
use App\Services\Production\ScheduleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CalendarController extends Controller
{
    public function index(Request $request, ScheduleService $schedule): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        if (now()->parse($validated['from'])->diffInDays(now()->parse($validated['to'])) > 366) {
            throw ValidationException::withMessages(['to' => ['The date range cannot exceed 366 days.']]);
        }

        return ApiResponse::success($schedule->calendar($validated['from'], $validated['to']));
    }

    public function blackouts(): JsonResponse
    {
        return ApiResponse::success([
            'items' => BlackoutDate::query()->orderBy('date')->get(['id', 'date', 'reason']),
        ]);
    }

    public function storeBlackout(BlackoutDateRequest $request): JsonResponse
    {
        $date = BlackoutDate::query()->create($request->validated());

        return ApiResponse::success($date, 'Blackout date created.', 201);
    }

    public function updateBlackout(BlackoutDateRequest $request, BlackoutDate $blackoutDate): JsonResponse
    {
        $blackoutDate->update($request->validated());

        return ApiResponse::success($blackoutDate, 'Blackout date updated.');
    }

    public function deleteBlackout(BlackoutDate $blackoutDate): JsonResponse
    {
        $blackoutDate->delete();

        return ApiResponse::success(null, 'Blackout date removed.');
    }
}
