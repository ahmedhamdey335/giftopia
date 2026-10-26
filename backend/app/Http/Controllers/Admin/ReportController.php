<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function overview(Request $request, ReportService $reports): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($reports->overview($from, $to));
    }

    public function estimates(Request $request, ReportService $reports): JsonResponse
    {
        [$from, $to] = $this->period($request);

        return ApiResponse::success($reports->estimates($from, $to));
    }

    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d'],
        ]);
        $from = Carbon::parse($validated['from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? now()->toDateString())->endOfDay();
        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['from' => ['The from date must not be after the to date.']]);
        }
        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['to' => ['The report range cannot exceed 366 days.']]);
        }

        return [$from, $to];
    }
}
