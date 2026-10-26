<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_upload_private_reencoded_inspiration_and_read_its_signed_url(): void
    {
        Storage::fake('private_assets');

        $response = $this->post('/api/studio/assets', [
            'files' => [UploadedFile::fake()->image('reference.png', 120, 80)],
        ])->assertCreated();

        $assetId = $response->json('data.items.0.id');
        $asset = Asset::query()->findOrFail($assetId);
        $this->assertNull($asset->owner_user_id);
        $this->assertSame('inspiration', $asset->kind);
        $this->assertSame('image/png', $asset->mime);
        $this->assertNotSame('reference.png', basename($asset->path));
        Storage::disk('private_assets')->assertExists($asset->path);

        $fileUrl = $response->json('data.items.0.file_url');
        $this->get($fileUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_upload_limits_files_and_rejects_non_image_content(): void
    {
        Storage::fake('private_assets');

        $this->post('/api/studio/assets', [
            'files' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
                UploadedFile::fake()->image('four.jpg'),
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['files']);

        $this->post('/api/studio/assets', [
            'files' => [UploadedFile::fake()->create('not-an-image.jpg', 10, 'text/plain')],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_customer_cannot_read_another_users_uploaded_asset(): void
    {
        Storage::fake('private_assets');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $response = $this->actingAs($owner, 'sanctum')->post('/api/studio/assets', [
            'files' => [UploadedFile::fake()->image('reference.webp', 100, 100)],
        ])->assertCreated();

        $this->actingAs($other, 'sanctum')
            ->get($response->json('data.items.0.file_url'))
            ->assertForbidden();
    }

    public function test_upload_reencoding_bounds_image_dimensions(): void
    {
        Storage::fake('private_assets');
        $response = $this->post('/api/studio/assets', [
            'files' => [UploadedFile::fake()->image('large.png', 2500, 1200)],
        ])->assertCreated();
        $asset = Asset::query()->findOrFail($response->json('data.items.0.id'));
        $dimensions = getimagesizefromstring(Storage::disk('private_assets')->get($asset->path));

        $this->assertSame(2048, $dimensions[0]);
        $this->assertSame(983, $dimensions[1]);
    }

    public function test_scheduler_purges_unattached_assets_older_than_24_hours(): void
    {
        Storage::fake('private_assets');
        $path = 'inspiration/expired.png';
        Storage::disk('private_assets')->put($path, 'test image');
        $asset = Asset::query()->create([
            'kind' => 'inspiration',
            'disk' => 'private_assets',
            'path' => $path,
            'mime' => 'image/png',
            'size' => 10,
            'original_name' => 'expired.png',
        ]);
        $asset->forceFill(['created_at' => now()->subHours(25)])->save();

        $this->artisan('giftopia:purge-orphan-assets')->assertSuccessful();

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
        Storage::disk('private_assets')->assertMissing($path);
    }
}
