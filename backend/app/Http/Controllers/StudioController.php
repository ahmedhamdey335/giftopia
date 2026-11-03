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
use App\Services\CatalogVersionService;
use App\Services\CompatibilityValidator;
use App\Services\Studio\PriceCalculator;
use App\Services\Studio\StudioConfiguration;
use App\Services\Studio\StudioQuoteService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public API entry points for the customer design flow: catalog, live checks, and quotes.
 *
 * Business rules and price calculations live in services so validation and quote creation
 * use the same domain behavior.
 */
class StudioController extends Controller
{
    /** Return the active design choices and settings, with an ETag for catalog revalidation. */
    public function catalog(Request $request, CatalogVersionService $versions): Response
    {
        $baseItems = BaseItem::query()->active()->orderBy('name')->get();
        $baseItemIds = $baseItems->modelKeys();

        $catalogVersion = $versions->current();
        $etag = '"'.$catalogVersion.'"';
        $conditionalTags = array_map('trim', explode(',', (string) $request->header('If-None-Match', '')));
        if (in_array($etag, $conditionalTags, true) || in_array('*', $conditionalTags, true)) {
            return response()->noContent(304)->header('ETag', $etag);
        }

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
            'expectation_copy_keys' => ['handmade_variation', 'refund_policy', 'proof_auto_approval'],
            'checkout' => [
                'delivery_cities' => config('studio.delivery.cities'),
                'delivery_governorates' => config('studio.delivery.governorates'),
                'delivery_fee' => (int) config('studio.delivery.fee', 0),
                'deposit_percent' => (int) config('studio.delivery.deposit_percent', 50),
                'lead_time_days' => (int) config('studio.delivery.lead_time_days', 7),
                'claim_window_days' => (int) config('studio.delivery.claim_window_days', 3),
                'proof_auto_approve_hours' => (int) config('studio.delivery.proof_auto_approve_hours', 48),
            ],
            'catalog_version' => $catalogVersion,
        ])->header('ETag', $etag);
    }

    /** Return live compatibility guidance and partial pricing without saving a quote. */
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

    /** Create a time-limited quote only after the complete design passes strict compatibility checks. */
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
