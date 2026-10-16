<?php

namespace Tests\Feature;

use App\Enums\ColorSwatchRole;
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
use App\Services\CompatibilityValidator;
use App\Services\Studio\StudioConfiguration;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_catalog_and_quote_factories_create_records(): void
    {
        $models = [
            BaseItem::factory()->create(),
            Mold::factory()->create(),
            Mood::factory()->create(),
            ColorSwatch::factory()->create(),
            Technique::factory()->create(),
            Insert::factory()->create(),
            Font::factory()->create(),
            TextColor::factory()->create(),
            OptionRule::factory()->create(),
            Quote::factory()->create(),
        ];

        foreach ($models as $model) {
            $this->assertTrue($model->exists);
        }
    }

    public function test_database_seeder_is_idempotent_and_has_the_five_spec_moods(): void
    {
        $this->seed(DatabaseSeeder::class);
        $counts = [
            BaseItem::count(),
            Mold::count(),
            Mood::count(),
            ColorSwatch::count(),
            Technique::count(),
            Insert::count(),
            Font::count(),
            TextColor::count(),
            OptionRule::count(),
            User::count(),
        ];

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, [
            BaseItem::count(),
            Mold::count(),
            Mood::count(),
            ColorSwatch::count(),
            Technique::count(),
            Insert::count(),
            Font::count(),
            TextColor::count(),
            OptionRule::count(),
            User::count(),
        ]);
        $this->assertEqualsCanonicalizing([
            'Summer/Cheerful',
            'Moody/Deep',
            'Romantic Pastel',
            'Vintage/Ancient',
            'Minimalist/Clean',
        ], Mood::query()->orderBy('name')->pluck('name')->all());
    }

    public function test_registration_ignores_requested_role_and_authenticates_with_tokens(): void
    {
        $registration = $this->postJson('/api/register', [
            'name' => '<b>New Customer</b>',
            'email' => 'new@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'admin',
        ]);

        $registration->assertCreated()
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonPath('data.user.name', 'New Customer');

        $user = User::query()->where('email', 'new@example.test')->firstOrFail();
        $this->assertSame('customer', $user->role);

        $token = $registration->json('data.token');
        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->withToken($token)->postJson('/api/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_is_rate_limited_and_api_errors_use_the_response_envelope(): void
    {
        User::factory()->create(['email' => 'login@example.test', 'password' => 'password123']);

        $this->postJson('/api/login', [
            'email' => 'login@example.test',
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('data.user.role', 'customer');

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $response = $this->postJson('/api/login', [
                'email' => 'login@example.test',
                'password' => 'incorrect-password',
            ]);
        }

        $response->assertStatus(429)->assertJsonPath('success', false);
        $this->getJson('/api/not-a-real-endpoint')
            ->assertNotFound()
            ->assertJsonPath('success', false);
        $this->deleteJson('/api/login')
            ->assertStatus(405)
            ->assertJsonPath('success', false);
    }

    public function test_admin_can_manage_catalog_but_customers_and_producers_are_restricted(): void
    {
        $item = BaseItem::factory()->create();
        $customer = User::factory()->create();
        $producer = User::factory()->create(['role' => 'producer']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($customer, 'sanctum')->postJson('/api/admin/catalog/base-items', [
            'name' => 'Denied',
            'slug' => 'denied',
            'base_price' => 10000,
            'piece_count' => 1,
            'labor_minutes' => 20,
            'cure_hours' => 24,
            'max_text_chars' => 0,
            'is_active' => true,
        ])->assertForbidden();

        $this->actingAs($producer, 'sanctum')->patchJson(
            '/api/admin/catalog/base-items/'.$item->id.'/availability',
            ['is_active' => false],
        )->assertOk()->assertJsonPath('data.is_active', false);

        $this->actingAs($producer, 'sanctum')->patchJson(
            '/api/admin/catalog/base-items/'.$item->id.'/availability',
            ['is_active' => true, 'base_price' => 1],
        )->assertUnprocessable();
        $this->assertSame($item->base_price, $item->fresh()->base_price);

        $this->actingAs($producer, 'sanctum')->patchJson(
            '/api/admin/catalog/base-items/'.$item->id,
            ['base_price' => 1],
        )->assertForbidden();

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/catalog/base-items', [
            'name' => 'Admin Item',
            'slug' => 'admin-item',
            'base_price' => 10000,
            'piece_count' => 1,
            'labor_minutes' => 20,
            'cure_hours' => 24,
            'max_text_chars' => 0,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.slug', 'admin-item');
    }

    public function test_admin_catalog_routes_cover_all_catalog_models_and_full_crud(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $base = BaseItem::factory()->create();
        $mold = Mold::factory()->create(['base_item_id' => $base->id]);
        $swatch = ColorSwatch::factory()->create(['role' => ColorSwatchRole::Base]);

        $this->actingAs($admin, 'sanctum');
        $this->postJson('/api/admin/catalog/molds', [
            'base_item_id' => $base->id,
            'name' => 'Admin Mold',
            'size_label' => '10 cm',
            'depth_mm' => 10,
            'price_delta' => 0,
        ])->assertCreated();
        $this->postJson('/api/admin/catalog/moods', [
            'name' => 'Admin Mood',
            'preset_json' => ['base_color' => $swatch->name],
            'is_active' => true,
        ])->assertCreated();
        $this->postJson('/api/admin/catalog/color-swatches', [
            'name' => 'Admin Swatch',
            'hex' => '#123456',
            'role' => 'accent',
            'price_delta' => 100,
            'is_available' => true,
        ])->assertCreated();
        $this->postJson('/api/admin/catalog/techniques', [
            'name' => 'Admin Technique',
            'price_delta' => 100,
            'labor_minutes' => 5,
            'requires_accent' => false,
        ])->assertCreated();
        $this->postJson('/api/admin/catalog/inserts', [
            'type' => 'flower',
            'name' => 'Admin Insert',
            'color' => null,
            'price' => 100,
            'labor_minutes' => 5,
            'min_depth_mm' => 2,
            'is_available' => true,
        ])->assertCreated();
        $this->postJson('/api/admin/catalog/fonts', [
            'name' => 'Admin Font',
            'price_delta' => 100,
        ])->assertCreated();
        $this->postJson('/api/admin/catalog/text-colors', [
            'name' => 'Admin Text Color',
            'price_delta' => 100,
        ])->assertCreated();
        $this->postJson('/api/admin/catalog/option-rules', [
            'subject_type' => 'base_item',
            'subject_id' => $base->id,
            'rule' => 'requires',
            'target_type' => 'mold',
            'target_id' => $mold->id,
            'message' => 'Choose this mold',
        ])->assertCreated();

        $font = Font::query()->where('name', 'Admin Font')->firstOrFail();
        $this->patchJson('/api/admin/catalog/fonts/'.$font->id, ['price_delta' => 200])
            ->assertOk()->assertJsonPath('data.price_delta', 200);
        $this->getJson('/api/admin/catalog/fonts')->assertOk()->assertJsonPath('data.items.0.name', 'Admin Font');
        $this->deleteJson('/api/admin/catalog/fonts/'.$font->id)->assertOk();
        $this->assertDatabaseMissing('fonts', ['id' => $font->id]);
    }

    public function test_admin_cannot_create_a_rule_with_an_unknown_morph_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $base = BaseItem::factory()->create();

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/catalog/option-rules', [
            'subject_type' => 'base_item',
            'subject_id' => $base->id,
            'rule' => 'blocks',
            'target_type' => 'unknown_type',
            'target_id' => 1,
            'message' => 'Invalid rule',
        ])->assertUnprocessable();
    }

    public function test_option_rule_requires_valid_referenced_ids(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/catalog/option-rules', [
            'subject_type' => 'base_item',
            'subject_id' => 999999,
            'rule' => 'blocks',
            'target_type' => 'mold',
            'target_id' => 999999,
            'message' => 'Invalid references',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['subject_id', 'target_id']);
    }

    public function test_catalog_item_referenced_by_a_quote_cannot_be_deleted(): void
    {
        $item = BaseItem::factory()->create();
        Quote::factory()->create([
            'base_item_id' => $item->id,
            'configuration' => ['base_item_id' => $item->id],
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->deleteJson('/api/admin/catalog/base-items/'.$item->id)
            ->assertStatus(409);
        $this->assertDatabaseHas('base_items', ['id' => $item->id]);
    }

    public function test_option_rules_block_and_require_options_with_strict_and_non_strict_modes(): void
    {
        $base = BaseItem::factory()->create();
        $mood = Mood::factory()->create();
        $mold = Mold::factory()->create(['base_item_id' => $base->id]);
        $otherMold = Mold::factory()->create(['base_item_id' => $base->id]);
        $technique = Technique::factory()->create();
        OptionRule::factory()->create([
            'subject_type' => 'technique',
            'subject_id' => $technique->id,
            'rule' => OptionRuleAction::Requires,
            'target_type' => 'mold',
            'target_id' => $mold->id,
            'message' => '',
        ]);
        OptionRule::factory()->create([
            'subject_type' => 'base_item',
            'subject_id' => $base->id,
            'rule' => OptionRuleAction::Blocks,
            'target_type' => 'mold',
            'target_id' => $otherMold->id,
            'message' => '',
        ]);

        $configuration = StudioConfiguration::fromArray([
            'base_item_id' => $base->id,
            'mood_id' => $mood->id,
            'mold_id' => $otherMold->id,
            'technique_id' => $technique->id,
        ]);
        $nonStrict = app(CompatibilityValidator::class)->validate($configuration);
        $strict = app(CompatibilityValidator::class)->validate($configuration, strict: true);

        $this->assertFalse($nonStrict->allowed);
        $this->assertFalse($strict->allowed);
        $messages = collect($strict->violations)->pluck('message')->all();
        $this->assertContains('This combination is not available.', $messages);
        $this->assertContains('A required option is missing from this configuration.', $messages);
    }

    public function test_non_strict_missing_requirement_is_a_warning_and_wrong_type_is_a_violation(): void
    {
        $base = BaseItem::factory()->create();
        $technique = Technique::factory()->create();
        $mold = Mold::factory()->create(['base_item_id' => $base->id]);
        $otherMold = Mold::factory()->create(['base_item_id' => $base->id]);
        OptionRule::factory()->create([
            'subject_type' => 'technique',
            'subject_id' => $technique->id,
            'rule' => OptionRuleAction::Requires,
            'target_type' => 'mold',
            'target_id' => $mold->id,
        ]);

        $missing = app(CompatibilityValidator::class)->validate(StudioConfiguration::fromArray([
            'base_item_id' => $base->id,
            'technique_id' => $technique->id,
        ]));
        $wrongType = app(CompatibilityValidator::class)->validate(StudioConfiguration::fromArray([
            'base_item_id' => $base->id,
            'technique_id' => $technique->id,
            'mold_id' => $otherMold->id,
        ]));

        $this->assertTrue($missing->allowed);
        $this->assertCount(1, $missing->warnings);
        $this->assertFalse($wrongType->allowed);
    }

    public function test_validator_enforces_depth_text_availability_mold_and_accent_rules(): void
    {
        $base = BaseItem::factory()->create(['max_text_chars' => 0]);
        $otherBase = BaseItem::factory()->create();
        $shallowMold = Mold::factory()->create(['base_item_id' => $otherBase->id, 'depth_mm' => 4]);
        $insert = Insert::factory()->create(['min_depth_mm' => 8, 'is_available' => false]);
        $swatch = ColorSwatch::factory()->create(['role' => ColorSwatchRole::Base, 'is_available' => false]);
        $technique = Technique::factory()->create(['requires_accent' => true]);

        $result = app(CompatibilityValidator::class)->validate(StudioConfiguration::fromArray([
            'base_item_id' => $base->id,
            'mold_id' => $shallowMold->id,
            'insert_ids' => [$insert->id],
            'base_color_id' => $swatch->id,
            'technique_id' => $technique->id,
            'personalization_text' => 'X',
        ]), strict: true);

        $messages = collect($result->violations)->pluck('message')->all();
        $this->assertContains('That mold does not belong to the selected item.', $messages);
        $this->assertContains($insert->name.' is not available right now.', $messages);
        $this->assertContains($insert->name.' needs at least 8.00mm of depth.', $messages);
        $this->assertContains('That base pigment is out of stock.', $messages);
        $this->assertContains($technique->name.' needs an accent color.', $messages);
        $this->assertContains('Personalization can be at most 0 characters.', $messages);
    }

    public function test_strict_validator_requires_mold_color_mood_and_technique(): void
    {
        $result = app(CompatibilityValidator::class)->validate(
            StudioConfiguration::fromArray(['base_item_id' => BaseItem::factory()->create()->id]),
            strict: true,
        );

        $this->assertFalse($result->allowed);
        $messages = collect($result->violations)->pluck('message')->all();
        $this->assertContains('Choose a mold.', $messages);
        $this->assertContains('Choose a base color.', $messages);
        $this->assertContains('Choose a mood.', $messages);
        $this->assertContains('Choose a technique.', $messages);
    }

    public function test_unavailable_swatches_and_unknown_option_ids_are_reported(): void
    {
        $swatch = ColorSwatch::factory()->create([
            'role' => ColorSwatchRole::Base,
            'is_available' => false,
        ]);

        $configuration = StudioConfiguration::fromArray([
            'base_item_id' => 999999,
            'base_color_id' => $swatch->id,
            'technique_id' => 888888,
            'insert_ids' => [777777],
        ]);
        $result = app(CompatibilityValidator::class)->validate($configuration);

        $messages = collect($result->violations)->pluck('message')->all();
        $this->assertContains('Unknown base item.', $messages);
        $this->assertContains('Unknown technique.', $messages);
        $this->assertContains('Unknown insert.', $messages);
        $this->assertContains('That base pigment is out of stock.', $messages);
    }
}
