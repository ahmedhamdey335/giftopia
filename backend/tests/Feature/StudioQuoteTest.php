<?php

namespace Tests\Feature;

use App\Enums\ColorSwatchRole;
use App\Enums\InsertType;
use App\Enums\OptionRuleAction;
use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\OptionRule;
use App\Models\Quote;
use App\Models\Technique;
use App\Models\TextColor;
use App\Models\User;
use App\Services\Studio\LeadTimeEstimator;
use App\Services\Studio\PriceCalculator;
use App\Services\Studio\SnapshotBuilder;
use App\Services\Studio\StudioConfiguration;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioQuoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_calculator_adds_each_surcharge_in_integer_minor_units(): void
    {
        $configuration = $this->completeConfiguration();
        $price = app(PriceCalculator::class)->calculate($configuration);

        $this->assertSame(14150, $price['total']);
        $this->assertSame('EGP', $price['currency']);
        $this->assertSame([
            'base_item',
            'mold',
            'base_color',
            'accent_color',
            'technique',
            'insert',
            'font',
            'text_color',
        ], array_column($price['items'], 'key'));
        $this->assertSame(14150, array_sum(array_column($price['items'], 'amount')));
    }

    public function test_personalization_fees_are_not_charged_without_personalization_text(): void
    {
        $base = BaseItem::factory()->create(['base_price' => 10000]);
        $font = Font::factory()->create(['price_delta' => 500]);
        $textColor = TextColor::factory()->create(['price_delta' => 250]);
        $configuration = StudioConfiguration::fromArray([
            'base_item_id' => $base->id,
            'font_id' => $font->id,
            'text_color_id' => $textColor->id,
        ]);

        $price = app(PriceCalculator::class)->calculate($configuration);

        $this->assertSame(10000, $price['total']);
        $this->assertSame(['base_item'], array_column($price['items'], 'key'));
    }

    public function test_lead_time_uses_the_work_week_across_friday_and_saturday(): void
    {
        $base = BaseItem::factory()->create(['labor_minutes' => 60, 'cure_hours' => 0]);
        $configuration = StudioConfiguration::fromArray(['base_item_id' => $base->id]);
        $estimator = app(LeadTimeEstimator::class);

        $friday = $estimator->estimate($configuration, CarbonImmutable::parse('2026-10-16 11:00:00', 'Africa/Cairo'));
        $saturday = $estimator->estimate($configuration, CarbonImmutable::parse('2026-10-17 11:00:00', 'Africa/Cairo'));

        $this->assertSame('2026-10-19 10:00:00', $friday->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-19 10:00:00', $saturday->format('Y-m-d H:i:s'));
    }

    public function test_lead_time_spills_thursday_work_into_sunday_then_adds_continuous_cure_and_packing(): void
    {
        $base = BaseItem::factory()->create(['labor_minutes' => 120, 'cure_hours' => 24]);
        $configuration = StudioConfiguration::fromArray(['base_item_id' => $base->id]);
        $readyAt = app(LeadTimeEstimator::class)->estimate(
            $configuration,
            CarbonImmutable::parse('2026-10-15 16:00:00', 'Africa/Cairo'),
        );

        $this->assertSame('2026-10-20 10:00:00', $readyAt->format('Y-m-d H:i:s'));
    }

    public function test_long_cure_time_runs_continuously_after_labor(): void
    {
        $base = BaseItem::factory()->create(['labor_minutes' => 30, 'cure_hours' => 48]);
        $configuration = StudioConfiguration::fromArray(['base_item_id' => $base->id]);
        $readyAt = app(LeadTimeEstimator::class)->estimate(
            $configuration,
            CarbonImmutable::parse('2026-10-18 10:00:00', 'Africa/Cairo'),
        );

        $this->assertSame('2026-10-21 10:30:00', $readyAt->format('Y-m-d H:i:s'));
    }

    public function test_snapshot_builder_contains_schema_version_and_named_priced_choices(): void
    {
        $configuration = $this->completeConfiguration();
        $price = app(PriceCalculator::class)->calculate($configuration);
        $snapshot = app(SnapshotBuilder::class)->build($configuration, $price);

        $this->assertSame(1, $snapshot['schema_version']);
        $this->assertSame($configuration->baseItem->id, $snapshot['base_item']['id']);
        $this->assertSame($configuration->baseItem->name, $snapshot['base_item']['name']);
        $this->assertSame($configuration->baseItem->base_price, $snapshot['base_item']['price']);
        $this->assertSame($configuration->mood->name, $snapshot['mood']['name']);
        $this->assertSame($configuration->baseColor->name, $snapshot['color']['base']['name']);
        $this->assertSame($configuration->accentColor->name, $snapshot['color']['accent']['name']);
        $this->assertSame($configuration->technique->price_delta, $snapshot['color']['technique']['price']);
        $this->assertSame($configuration->inserts->first()->price, $snapshot['inserts'][0]['price']);
        $this->assertSame($price['total'], $snapshot['price']['total']);
        $this->assertSame($configuration->totalLaborMinutes(), $snapshot['labor_minutes']);
    }

    public function test_quote_endpoint_returns_a_persisted_guest_quote_with_a_deterministic_hash(): void
    {
        $configuration = $this->completeConfiguration();
        $input = $configuration->normalizedConfiguration();

        $first = $this->postJson('/api/studio/quote', $input)->assertCreated();
        $second = $this->postJson('/api/studio/quote', $input)->assertCreated();

        $quote = Quote::query()->findOrFail($first->json('data.quote_id'));
        $this->assertNull($quote->user_id);
        $secondQuote = Quote::query()->findOrFail($second->json('data.quote_id'));
        $this->assertSame($quote->config_hash, $secondQuote->config_hash);
        $this->assertSame($quote->total_price, $secondQuote->total_price);
        $this->assertSame($quote->price_breakdown['total'], $quote->total_price);
        $this->assertSame($quote->total_price, $first->json('data.total'));
        $this->assertNotNull($first->json('data.ready_at'));
        $this->assertNotNull($first->json('data.expires_at'));
    }

    public function test_quote_endpoint_validates_strict_configuration_and_unknown_ids(): void
    {
        $this->postJson('/api/studio/quote', [])->assertUnprocessable()
            ->assertJsonPath('errors.violations.0.message', 'Choose an item to quote.');

        $this->postJson('/api/studio/quote', ['base_item_id' => 999999])->assertUnprocessable()
            ->assertJsonFragment(['message' => 'Unknown base item.']);
    }

    public function test_quote_endpoint_rejects_a_blocked_option_combination(): void
    {
        $configuration = $this->completeConfiguration();
        $forbiddenTechnique = Technique::factory()->create();
        OptionRule::factory()->create([
            'subject_type' => 'base_item',
            'subject_id' => $configuration->baseItem->id,
            'rule' => OptionRuleAction::Blocks,
            'target_type' => 'technique',
            'target_id' => $configuration->technique->id,
            'message' => 'This technique cannot be used here.',
        ]);

        $this->postJson('/api/studio/quote', $configuration->normalizedConfiguration())
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'This technique cannot be used here.']);
    }

    public function test_quote_is_owned_by_authenticated_user_and_expires_after_configured_ttl(): void
    {
        $user = User::factory()->create();
        $configuration = $this->completeConfiguration();
        $token = $user->createToken('studio')->plainTextToken;
        $response = $this->withToken($token)
            ->postJson('/api/studio/quote', $configuration->normalizedConfiguration())
            ->assertCreated();
        $quote = Quote::query()->findOrFail($response->json('data.quote_id'));

        $this->assertSame($user->id, $quote->user_id);
        $this->assertTrue($quote->expires_at->between(now()->addMinutes(29), now()->addMinutes(30)));
        $quote->expires_at = now()->subSecond();
        $this->assertTrue($quote->isExpired());

        $this->withToken('invalid-token')
            ->postJson('/api/studio/quote', $configuration->normalizedConfiguration())
            ->assertUnauthorized();
    }

    public function test_catalog_endpoint_groups_only_active_and_available_options(): void
    {
        $availableItem = BaseItem::factory()->create(['is_active' => true]);
        $inactiveItem = BaseItem::factory()->create(['is_active' => false]);
        $availableMood = Mood::factory()->create(['is_active' => true]);
        Mood::factory()->create(['is_active' => false]);
        Mold::factory()->create(['base_item_id' => $availableItem->id]);
        Mold::factory()->create(['base_item_id' => $inactiveItem->id]);
        ColorSwatch::factory()->create(['role' => ColorSwatchRole::Base, 'is_available' => true]);
        ColorSwatch::factory()->create(['role' => ColorSwatchRole::Base, 'is_available' => false]);

        $response = $this->getJson('/api/studio/catalog')->assertOk();
        $this->assertCount(1, $response->json('data.steps.base_items'));
        $this->assertCount(1, $response->json('data.steps.molds'));
        $this->assertCount(1, $response->json('data.steps.moods'));
        $this->assertSame($availableMood->id, $response->json('data.steps.moods.0.id'));
        $this->assertContains('handmade_variation', $response->json('data.expectation_copy_keys'));
    }

    public function test_validation_endpoint_returns_compatibility_and_a_partial_price(): void
    {
        $base = BaseItem::factory()->create(['base_price' => 5000]);

        $response = $this->postJson('/api/studio/validate', ['base_item_id' => $base->id])->assertOk();
        $this->assertTrue($response->json('data.allowed'));
        $this->assertSame(5000, $response->json('data.price.total'));
    }

    public function test_studio_request_limits_free_text_and_rejects_unimplemented_asset_ids(): void
    {
        $this->postJson('/api/studio/validate', ['personalization_text' => str_repeat('x', 301)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['personalization_text']);
        $this->postJson('/api/studio/validate', ['note' => str_repeat('x', 301)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);
        $this->postJson('/api/studio/validate', ['inspiration_asset_ids' => [1]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inspiration_asset_ids']);
    }

    private function completeConfiguration(): StudioConfiguration
    {
        $base = BaseItem::factory()->create([
            'base_price' => 10000,
            'labor_minutes' => 60,
            'cure_hours' => 24,
            'max_text_chars' => 20,
        ]);
        $mold = Mold::factory()->create([
            'base_item_id' => $base->id,
            'depth_mm' => 10,
            'price_delta' => 500,
        ]);
        $mood = Mood::factory()->create();
        $baseColor = ColorSwatch::factory()->create([
            'role' => ColorSwatchRole::Base,
            'price_delta' => 300,
        ]);
        $accentColor = ColorSwatch::factory()->create([
            'role' => ColorSwatchRole::Accent,
            'price_delta' => 2000,
        ]);
        $technique = Technique::factory()->create([
            'price_delta' => 1000,
            'labor_minutes' => 15,
            'requires_accent' => true,
        ]);
        $insert = Insert::factory()->create([
            'type' => InsertType::Flower,
            'price' => 200,
            'labor_minutes' => 5,
            'min_depth_mm' => 5,
        ]);
        $font = Font::factory()->create(['price_delta' => 100]);
        $textColor = TextColor::factory()->create(['price_delta' => 50]);

        return StudioConfiguration::fromArray([
            'base_item_id' => $base->id,
            'mold_id' => $mold->id,
            'mood_id' => $mood->id,
            'base_color_id' => $baseColor->id,
            'accent_color_id' => $accentColor->id,
            'technique_id' => $technique->id,
            'insert_ids' => [$insert->id],
            'font_id' => $font->id,
            'text_color_id' => $textColor->id,
            'personalization_text' => 'Gift',
            'personalization_note' => 'Please center the text.',
        ]);
    }
}
