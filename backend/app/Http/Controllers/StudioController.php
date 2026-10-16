<?php

namespace App\Http\Controllers;

use App\Http\Requests\Studio\StudioConfigurationRequest;
use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\OptionRule;
use App\Models\Technique;
use App\Models\TextColor;
use App\Services\CompatibilityValidator;
use App\Services\Studio\PriceCalculator;
use App\Services\Studio\StudioConfiguration;
use App\Services\Studio\StudioQuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class StudioController extends Controller
{
    public function catalog(): JsonResponse
    {
        $baseItems = BaseItem::query()->active()->orderBy('name')->get();
        $baseItemIds = $baseItems->modelKeys();

        return ApiResponse::success([
            'steps' => [
                'base_items' => $baseItems,
                'molds' => Mold::query()->whereIn('base_item_id', $baseItemIds)->orderBy('name')->get(),
                'moods' => Mood::query()->active()->orderBy('name')->get(),
                'colors' => [
                    'base' => ColorSwatch::query()->where('role', 'base')->available()->orderBy('name')->get(),
                    'accent' => ColorSwatch::query()->where('role', 'accent')->available()->orderBy('name')->get(),
                ],
                'techniques' => Technique::query()->orderBy('name')->get(),
                'inserts' => Insert::query()->available()->orderBy('name')->get(),
                'personalization' => [
                    'fonts' => Font::query()->orderBy('name')->get(),
                    'text_colors' => TextColor::query()->orderBy('name')->get(),
                ],
            ],
            'rules' => OptionRule::query()->orderBy('id')->get(),
            'expectation_copy_keys' => ['handmade_variation', 'non_returnable'],
        ]);
    }

    public function validateConfiguration(
        StudioConfigurationRequest $request,
        CompatibilityValidator $validator,
        PriceCalculator $prices,
    ): JsonResponse {
        $configuration = StudioConfiguration::fromArray($request->validated());
        $result = $validator->validate($configuration);
        $data = $result->toArray();
        $data['price'] = $prices->calculate($configuration);

        return ApiResponse::success($data, $result->allowed ? 'Configuration is valid.' : 'Configuration has issues.');
    }

    public function quote(
        StudioConfigurationRequest $request,
        CompatibilityValidator $validator,
        StudioQuoteService $quotes,
    ): JsonResponse {
        $configuration = StudioConfiguration::fromArray($request->validated());
        $result = $validator->validate($configuration, strict: true);
        if (! $result->allowed) {
            return ApiResponse::error('Configuration cannot be quoted.', [
                'violations' => $result->violations,
            ], 422);
        }

        $quote = $quotes->create($configuration, $request->user());

        return ApiResponse::success($quotes->response($quote), 'Quote created.', 201);
    }
}
